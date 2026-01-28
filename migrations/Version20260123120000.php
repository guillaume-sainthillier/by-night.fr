<?php

declare(strict_types=1);

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

/**
 * Add event_timesheet table to store multiple date/time entries per event.
 */
final class Version20260123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_timesheet table for storing multiple timetables per event';
    }

    public function up(Schema $schema): void
    {
        // Create event_timesheet table
        $this->addSql('CREATE TABLE event_timesheet (
            id INT AUTO_INCREMENT NOT NULL,
            event_id INT NOT NULL,
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            hours VARCHAR(256) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX event_timesheet_event_idx (event_id),
            INDEX event_timesheet_start_idx (start_at),
            INDEX event_timesheet_end_idx (end_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE event_timesheet ADD CONSTRAINT FK_event_timesheet_event FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');

        // Migrate existing event data: create one timesheet entry per event
        // Use TIMESTAMP to properly convert DATE to DATETIME
        $this->addSql('INSERT INTO event_timesheet (event_id, start_at, end_at, hours, created_at, updated_at)
            SELECT
                id,
                TIMESTAMP(start_date, \'00:00:00\'),
                TIMESTAMP(COALESCE(end_date, start_date), \'23:59:59\'),
                hours,
                NOW(),
                NOW()
            FROM `event`
            WHERE start_date IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_timesheet DROP FOREIGN KEY FK_event_timesheet_event');
        $this->addSql('DROP TABLE event_timesheet');
    }
}
