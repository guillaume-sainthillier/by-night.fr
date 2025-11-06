<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Event;
use DateTime;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Event>
 */
final class EventFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Event::class;
    }

    protected function defaults(): array
    {
        $startDate = self::faker()->dateTimeBetween('now', '+3 months');
        $endDate = (clone $startDate)->modify('+' . self::faker()->numberBetween(0, 7) . ' days');

        return [
            'name' => self::faker()->sentence(4),
            'description' => self::faker()->paragraphs(3, true),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'place' => PlaceFactory::new(),
            'placeName' => self::faker()->company(),
            'user' => UserFactory::new(),
            'latitude' => self::faker()->latitude(),
            'longitude' => self::faker()->longitude(),
            'category' => self::faker()->randomElement(['Concert', 'Théâtre', 'Exposition', 'Festival', 'Sport']),
            'theme' => self::faker()->randomElement(['Rock', 'Jazz', 'Classique', 'Pop', 'Electro']),
            'type' => self::faker()->randomElement(['Concert', 'Spectacle', 'Festival']),
        ];
    }

    public function upcoming(): self
    {
        $startDate = self::faker()->dateTimeBetween('now', '+3 months');
        $endDate = (clone $startDate)->modify('+' . self::faker()->numberBetween(0, 7) . ' days');

        return $this->with([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function past(): self
    {
        $startDate = self::faker()->dateTimeBetween('-3 months', '-1 day');
        $endDate = (clone $startDate)->modify('+' . self::faker()->numberBetween(0, 7) . ' days');

        return $this->with([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function withDates(DateTime $startDate, ?DateTime $endDate = null): self
    {
        return $this->with([
            'startDate' => $startDate,
            'endDate' => $endDate ?? $startDate,
        ]);
    }
}
