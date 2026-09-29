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

final class Version20260929180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the number of events to come of each agenda type on cities and countries (recounted by app:events:count-upcoming); the portals show no count until its first run';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone ADD upcoming_agenda_types JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE country ADD upcoming_agenda_types JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone DROP upcoming_agenda_types');
        $this->addSql('ALTER TABLE country DROP upcoming_agenda_types');
    }
}
