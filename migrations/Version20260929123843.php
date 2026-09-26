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

final class Version20260929123843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the agenda types of each event (filled by app:events:classify-agenda-types), which the counts of the agenda type links read';
    }

    public function up(Schema $schema): void
    {
        // Added at the end of the table: MySQL 8 does it instantly, without rebuilding the event table
        $this->addSql('ALTER TABLE event ADD agenda_types TINYTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP agenda_types');
    }
}
