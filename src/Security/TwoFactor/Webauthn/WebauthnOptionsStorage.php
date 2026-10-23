<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\TwoFactor\Webauthn;

use Symfony\Component\HttpFoundation\RequestStack;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Keeps in the session the options last sent to the browser, so that its response is checked against their challenge:
 * one for a login (the 2FA form), one for a registration (the new passkey form).
 */
final readonly class WebauthnOptionsStorage
{
    private const string REQUEST_KEY = '_webauthn_request_options';

    private const string CREATION_KEY = '_webauthn_creation_options';

    public function __construct(
        private RequestStack $requestStack,
        private WebauthnCeremonies $ceremonies,
    ) {
    }

    public function setRequestOptions(PublicKeyCredentialRequestOptions $options): void
    {
        $this->requestStack->getSession()->set(self::REQUEST_KEY, $this->ceremonies->toJson($options));
    }

    public function getRequestOptions(): ?PublicKeyCredentialRequestOptions
    {
        return $this->get(self::REQUEST_KEY, PublicKeyCredentialRequestOptions::class);
    }

    public function setCreationOptions(PublicKeyCredentialCreationOptions $options): void
    {
        $this->requestStack->getSession()->set(self::CREATION_KEY, $this->ceremonies->toJson($options));
    }

    public function getCreationOptions(): ?PublicKeyCredentialCreationOptions
    {
        return $this->get(self::CREATION_KEY, PublicKeyCredentialCreationOptions::class);
    }

    /**
     * Once checked: a challenge is answered only once
     */
    public function clearCreationOptions(): void
    {
        $this->requestStack->getSession()->remove(self::CREATION_KEY);
    }

    /**
     * @template T of PublicKeyCredentialOptions
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function get(string $key, string $class): ?PublicKeyCredentialOptions
    {
        $json = $this->requestStack->getSession()->get($key);
        if (!\is_string($json)) {
            return null;
        }

        return $this->ceremonies->getSerializer()->deserialize($json, $class, 'json');
    }
}
