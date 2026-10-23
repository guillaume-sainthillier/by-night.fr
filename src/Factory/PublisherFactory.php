<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Publisher;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Publisher>
 */
final class PublisherFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Publisher::class;
    }

    /**
     * For a beforeInstantiate hook: gives what a member made (an event, an import, an export) the publisher they own,
     * as the site does, the first one, else a new one. Given a 'publisher' (null for none), or not persisted, the
     * object is left as is.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public static function forTheMember(array $parameters, bool $persisting): array
    {
        $user = $parameters['user'] ?? null;
        if (null === $user || \array_key_exists('publisher', $parameters) || !$persisting) {
            return $parameters;
        }

        // The default member is created now, to be the publisher's owner too
        if ($user instanceof UserFactory) {
            $user = $parameters['user'] = $user->create();
        }

        $parameters['publisher'] = self::repository()->findOneBy(['owner' => $user]) ?? self::createOne(['owner' => $user]);

        return $parameters;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'owner' => UserFactory::new(),
        ];
    }
}
