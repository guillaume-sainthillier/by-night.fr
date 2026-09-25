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

final class Version20260925134823 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the upcoming published events, so a country page no longer scans every canonical event (~8 s for France)';
    }

    public function up(Schema $schema): void
    {
        // Built online (MySQL 8 in-place ADD INDEX): a few seconds on a copy of production, reads and writes go on meanwhile
        $this->addSql('CREATE INDEX event_upcoming_idx ON event (duplicate_of_id, draft, end_date, participations, place_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX event_upcoming_idx ON event');
    }
}
