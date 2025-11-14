<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2025 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrate existing event date data to event_date_time table.
 */
final class Version20250104000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate existing event dates to event_date_time table';
    }

    public function up(Schema $schema): void
    {
        // Migrate existing events to have one event_date_time entry
        // Convert DATE fields to DATETIME by adding time component
        // start_date gets 00:00:00, end_date gets 23:59:59
        $this->addSql("
            INSERT INTO event_date_time (event_id, start_date_time, end_date_time)
            SELECT
                id,
                CONCAT(start_date, ' 00:00:00') as start_date_time,
                CONCAT(end_date, ' 23:59:59') as end_date_time
            FROM `event`
            WHERE start_date IS NOT NULL
            AND end_date IS NOT NULL
        ");
    }

    public function down(Schema $schema): void
    {
        // Revert: delete all event_date_time entries
        // Note: The original start_date and end_date fields are still intact
        $this->addSql('DELETE FROM event_date_time');
    }
}
