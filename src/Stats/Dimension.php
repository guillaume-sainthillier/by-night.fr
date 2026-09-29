<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

/**
 * What an UpcomingCount row counts the events of a zone by: each dimension has a column of its own, null on the rows
 * of the other.
 */
enum Dimension
{
    case Category;
    case AgendaType;

    /** The column of the upcoming_count table */
    public function column(): string
    {
        return match ($this) {
            self::Category => 'tag_id',
            self::AgendaType => 'agenda_type',
        };
    }

    /** The field of UpcomingCount */
    public function field(): string
    {
        return match ($this) {
            self::Category => 'tag',
            self::AgendaType => 'agendaType',
        };
    }
}
