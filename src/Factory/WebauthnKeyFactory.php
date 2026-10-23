<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\WebauthnKey;
use Override;
use Symfony\Component\Uid\Uuid;
use Webauthn\TrustPath\EmptyTrustPath;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * A passkey with a random credential: enough for the pages that list, rename or delete passkeys. A login with a real
 * signature takes tests/Security/TwoFactor/SoftwarePasskey.
 *
 * @extends PersistentObjectFactory<WebauthnKey>
 */
final class WebauthnKeyFactory extends PersistentObjectFactory
{
    #[Override]
    public static function class(): string
    {
        return WebauthnKey::class;
    }

    #[Override]
    protected function defaults(): array
    {
        return [
            'publicKeyCredentialId' => random_bytes(16),
            'type' => 'public-key',
            'transports' => ['internal'],
            'attestationType' => 'none',
            'trustPath' => new EmptyTrustPath(),
            'aaguid' => Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            'credentialPublicKey' => random_bytes(77),
            'userHandle' => '1',
            'counter' => 0,
            'name' => self::faker()->unique()->word(),
            'user' => UserFactory::new(),
        ];
    }
}
