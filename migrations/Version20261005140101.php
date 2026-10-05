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

final class Version20261005140101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the times the sessions start and end, and the time the last session of the events ends';
    }

    public function up(Schema $schema): void
    {
        // INSTANT: nullable columns at the end only change the metadata of the 6 GB event table and of the 4M timesheets
        $this->addSql('ALTER TABLE `event` ADD end_time TIME DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('ALTER TABLE event_timesheet ADD start_time TIME DEFAULT NULL, ADD end_time TIME DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_timesheet DROP COLUMN start_time, DROP COLUMN end_time, ALGORITHM=INSTANT');
        $this->addSql('ALTER TABLE `event` DROP COLUMN end_time, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
