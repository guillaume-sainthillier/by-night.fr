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
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;

/**
 * The registration ceremony of a new passkey (App\Controller\User\SecurityController).
 */
final readonly class WebauthnRegistration
{
    public function __construct(
        private WebauthnCeremonies $ceremonies,
        private WebauthnOptionsStorage $storage,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The options of the new passkey form, as JSON for the browser. A passkey already registered is excluded: the
     * browser says so instead of registering it twice.
     */
    public function createOptionsJson(User $user): string
    {
        $options = PublicKeyCredentialCreationOptions::create(
            rp: $this->ceremonies->getRpEntity(),
            user: $user->getWebauthnUser(),
            challenge: random_bytes(32),
            pubKeyCredParams: $this->ceremonies->getCredentialParameters(),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $user->getWebauthnKeys()
                ->map(static fn (WebauthnKey $key): PublicKeyCredentialDescriptor => $key->getPublicKeyCredentialDescriptor())
                ->getValues(),
            timeout: WebauthnCeremonies::TIMEOUT,
        );
        $this->storage->setCreationOptions($options);

        return $this->ceremonies->toJson($options);
    }

    /**
     * The passkey of the browser's response, added to the user (the caller flushes)
     *
     * @throws RuntimeException when the response does not answer the options last sent
     */
    public function register(User $user, string $response, string $name): WebauthnKey
    {
        $options = $this->storage->getCreationOptions();
        $request = $this->requestStack->getMainRequest();
        if (null === $options || null === $request) {
            throw new RuntimeException('No passkey registration in progress.');
        }

        $this->storage->clearCreationOptions();

        $credential = $this->ceremonies->getSerializer()->deserialize($response, PublicKeyCredential::class, 'json');
        $attestation = $credential->response;
        if (!$attestation instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException('The response is not a passkey registration.');
        }

        $record = $this->ceremonies->getAttestationResponseValidator()->check(
            authenticatorAttestationResponse: $attestation,
            publicKeyCredentialCreationOptions: $options,
            host: $request->getHost(),
        );

        return WebauthnKey::fromCredentialRecord($record, $user, $name);
    }
}
