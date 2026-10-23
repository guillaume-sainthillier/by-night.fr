<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor\Webauthn;

use App\Entity\User;
use App\Entity\WebauthnKey;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The login ceremony of the passkey second factor: the options of the 2FA form, then the check of the browser's answer.
 */
final readonly class WebauthnAuthenticator
{
    public function __construct(
        private WebauthnCeremonies $ceremonies,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * A fresh challenge, answerable by the user's own passkeys only
     */
    public function createRequestOptions(User $user): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            allowCredentials: $user->getWebauthnKeys()
                ->map(static fn (WebauthnKey $key): PublicKeyCredentialDescriptor => $key->getPublicKeyCredentialDescriptor())
                ->getValues(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: WebauthnCeremonies::TIMEOUT,
        );
    }

    /**
     * True when the response is the assertion of one of the user's passkeys for these options. The passkey then
     * remembers its new signature counter and when it was used.
     */
    public function check(User $user, PublicKeyCredentialRequestOptions $options, string $response): bool
    {
        // The denormalizer of the library dereferences these members unguarded: anything else (a TOTP code typed in
        // the other form, the empty field of a cancelled prompt) would be a TypeError, so a 500, not a wrong code
        if (!$this->isWellFormedAssertion($response)) {
            return false;
        }

        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return false;
        }

        try {
            $credential = $this->ceremonies->getSerializer()->deserialize($response, PublicKeyCredential::class, 'json');
            $assertion = $credential->response;
            if (!$assertion instanceof AuthenticatorAssertionResponse) {
                return false;
            }

            $key = $user->getWebauthnKeys()->findFirst(
                static fn (int $index, WebauthnKey $key): bool => hash_equals($key->publicKeyCredentialId, $credential->rawId),
            );
            if (null === $key) {
                return false;
            }

            $record = $this->ceremonies->getAssertionResponseValidator()->check(
                credentialRecord: $key,
                authenticatorAssertionResponse: $assertion,
                publicKeyCredentialRequestOptions: $options,
                host: $request->getHost(),
                userHandle: $user->getWebauthnUser()->id,
            );
        } catch (Throwable $e) {
            // A wrong signature, challenge or origin: the library tells it by an exception
            $this->logger->notice('Passkey second factor refused.', ['exception' => $e, 'user_id' => $user->getId()]);

            return false;
        }

        $key->markAsUsed($record->counter);
        $this->entityManager->flush();

        return true;
    }

    private function isWellFormedAssertion(string $response): bool
    {
        try {
            $decoded = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return \is_array($decoded)
            && \is_string($decoded['id'] ?? null)
            && \is_string($decoded['rawId'] ?? null)
            && \is_string($decoded['type'] ?? null)
            && \is_array($decoded['response'] ?? null);
    }
}
