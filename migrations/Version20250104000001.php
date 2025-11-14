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
 * Create event_date_time table to support multiple date/time slots per event.
 */
final class Version20250104000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_date_time table for multiple date/time slots per event';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_date_time (
            id INT AUTO_INCREMENT NOT NULL,
            event_id INT NOT NULL,
            start_date_time DATETIME NOT NULL,
            end_date_time DATETIME NOT NULL,
            INDEX event_date_time_start_idx (start_date_time),
            INDEX event_date_time_end_idx (end_date_time),
            INDEX event_date_time_event_start_idx (event_id, start_date_time),
            INDEX IDX_8F82DAF971F7E88B (event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_date_time ADD CONSTRAINT FK_8F82DAF971F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_date_time DROP FOREIGN KEY FK_8F82DAF971F7E88B');
        $this->addSql('DROP TABLE event_date_time');
    }
}
