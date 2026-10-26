<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor;

use App\Entity\User;
use App\Entity\WebauthnKey;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\TFAWebauthn\Model\TwoFactorInterface;
use Jbtronics\TFAWebauthn\Security\TwoFactor\Provider\Webauthn\WebauthnAuthenticatorInterface;
use JsonException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\Util\Base64;

/**
 * The login ceremony of jbtronics/2fa-webauthn, with what the site adds to it: an answer that is no assertion at all is
 * a wrong code, refused before the library (which would log it as an error), and the passkey that answered remembers
 * when it was used and its new signature counter (set by the library's validator on the entity itself).
 */
#[AsDecorator('jbtronics_webauthn_tfa.webauthn_authenticator')]
final readonly class PasskeyAuthenticator implements WebauthnAuthenticatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private WebauthnAuthenticatorInterface $inner,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function generateAuthenticationRequest(TwoFactorInterface $user): PublicKeyCredentialRequestOptions
    {
        return $this->inner->generateAuthenticationRequest($user);
    }

    #[Override]
    public function checkAuthenticationResponse(TwoFactorInterface $user, PublicKeyCredentialRequestOptions $request, string $response): bool
    {
        // A TOTP code typed in the passkey form, the empty field of a cancelled prompt
        $rawId = $this->getRawId($response);
        if (null === $rawId || !$this->inner->checkAuthenticationResponse($user, $request, $response)) {
            return false;
        }

        if ($user instanceof User) {
            $user->getWebauthnKeys()
                ->findFirst(static fn (int $index, WebauthnKey $key): bool => hash_equals($key->publicKeyCredentialId, $rawId))
                ?->markAsUsed();
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * The binary id of the passkey that answered, null when the response is no assertion
     */
    private function getRawId(string $response): ?string
    {
        try {
            $decoded = json_decode($response, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!\is_array($decoded)
            || !\is_string($decoded['id'] ?? null)
            || !\is_string($decoded['rawId'] ?? null)
            || !\is_string($decoded['type'] ?? null)
            || !\is_array($decoded['response'] ?? null)) {
            return null;
        }

        try {
            return Base64::decode($decoded['rawId']);
        } catch (InvalidDataException) {
            return null;
        }
    }
}
