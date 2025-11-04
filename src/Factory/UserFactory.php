<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'username' => self::faker()->unique()->userName(),
            'email' => self::faker()->unique()->email(),
            'password' => self::faker()->password(),
            'enabled' => true,
            'roles' => ['ROLE_USER'],
            'firstname' => self::faker()->firstName(),
            'lastname' => self::faker()->lastName(),
        ];
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->with(['enabled' => $enabled]);
    }

    public function withRole(string $role): self
    {
        return $this->with(['roles' => ['ROLE_USER', $role]]);
    }

    public function admin(): self
    {
        return $this->withRole('ROLE_ADMIN');
    }
}
