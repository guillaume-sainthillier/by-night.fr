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

final class Version20260921162439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Event families: identity_hash on event (the same event imported under distinct external ids) and source_event_id on event_timesheet (the dates a canonical inherits from a duplicate sibling, gone with it)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` ADD identity_hash VARCHAR(40) DEFAULT NULL');
        $this->addSql('CREATE INDEX event_identity_hash_idx ON `event` (identity_hash)');

        // Index first so the foreign key reuses it instead of creating its own
        $this->addSql('ALTER TABLE event_timesheet ADD source_event_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX event_timesheet_source_event_idx ON event_timesheet (source_event_id)');
        $this->addSql('ALTER TABLE event_timesheet ADD CONSTRAINT FK_event_timesheet_source_event FOREIGN KEY (source_event_id) REFERENCES `event` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_timesheet DROP FOREIGN KEY FK_event_timesheet_source_event');
        $this->addSql('DROP INDEX event_timesheet_source_event_idx ON event_timesheet');
        $this->addSql('ALTER TABLE event_timesheet DROP source_event_id');

        $this->addSql('DROP INDEX event_identity_hash_idx ON `event`');
        $this->addSql('ALTER TABLE `event` DROP identity_hash');
    }
}
