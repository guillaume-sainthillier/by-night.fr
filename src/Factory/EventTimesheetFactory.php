<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\EventTimesheet;
use DateTimeImmutable;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<EventTimesheet>
 */
final class EventTimesheetFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return EventTimesheet::class;
    }

    protected function defaults(): array
    {
        $date = DateTimeImmutable::createFromInterface(self::faker()->dateTimeBetween('now', '+3 months'))->setTime(0, 0);

        return [
            'event' => EventFactory::new(),
            'startAt' => $date,
            'endAt' => $date,
            'startTime' => new DateTimeImmutable('20:00'),
        ];
    }

    /**
     * A single-day timesheet on the given date.
     */
    public function on(string $date, ?string $hours = null): self
    {
        return $this->with([
            'startAt' => new DateTimeImmutable($date),
            'endAt' => new DateTimeImmutable($date),
            'hours' => $hours,
        ]);
    }
}
