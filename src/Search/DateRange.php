<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;

/**
 * A period of whole days: from a day, to another one or with no end. The agenda searches the events with a session in
 * it, a date shortcut (DateRangePreset) is one.
 */
final readonly class DateRange
{
    public DateTimeImmutable $from;

    public ?DateTimeImmutable $to;

    public function __construct(DateTimeInterface $from, ?DateTimeInterface $to = null)
    {
        $this->from = DateTimeImmutable::createFromInterface($from)->setTime(0, 0);
        $this->to = null !== $to ? DateTimeImmutable::createFromInterface($to)->setTime(0, 0) : null;
    }

    /**
     * "Le 3 oct. 2026", "Du 3 oct. 2026 au 5 oct. 2026", "À partir du 3 oct. 2026", as the date picker writes them
     * (assets/js/services/ui/DatepickerService.js).
     */
    public function label(): string
    {
        $formatter = IntlDateFormatter::create(null, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        $from = (string) $formatter->format($this->from);

        return match (true) {
            null === $this->to => \sprintf('À partir du %s', $from),
            $this->from == $this->to => \sprintf('Le %s', $from),
            default => \sprintf('Du %s au %s', $from, $formatter->format($this->to)),
        };
    }

    /**
     * The dates as the agenda reads them from its query string: "?dateRange[from]=2026-10-03&dateRange[to]=2026-10-05".
     *
     * @return array{from: string, to?: string}
     */
    public function toQuery(): array
    {
        $query = ['from' => $this->from->format('Y-m-d')];
        if (null !== $this->to) {
            $query['to'] = $this->to->format('Y-m-d');
        }

        return $query;
    }
}
