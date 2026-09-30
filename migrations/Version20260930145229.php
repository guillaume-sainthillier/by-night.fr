<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930145229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give the events saved without dates (member form before a0949905, e.g. 3370243) the span of their own timesheets';
    }

    public function up(Schema $schema): void
    {
        // The span of the event's own timesheets, not of the ones copied from a sibling (source_event_id)
        $this->addSql(<<<'SQL'
            UPDATE event e
            JOIN (
                SELECT event_id, MIN(start_at) AS first_day, MAX(end_at) AS last_day FROM event_timesheet
                WHERE source_event_id IS NULL
                GROUP BY event_id
            ) span ON span.event_id = e.id
            SET e.start_date = COALESCE(e.start_date, span.first_day), e.end_date = COALESCE(e.end_date, span.last_day)
            WHERE e.start_date IS NULL OR e.end_date IS NULL
            SQL);
        // An event without an end ends the day it starts, as Event::setStartDate() does
        $this->addSql('UPDATE event SET end_date = start_date WHERE end_date IS NULL AND start_date IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Emptying the dates again would only bring back the 500s of these events' pages
        $this->throwIrreversibleMigrationException();
    }
}
