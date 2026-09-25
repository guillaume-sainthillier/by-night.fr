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

final class Version20260925141552 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the published events of a place by end date, so a city page no longer reads every past event of its places';
    }

    public function up(Schema $schema): void
    {
        // The new index first: the city queries always have a place index to fall back on
        $this->addSql('CREATE INDEX event_place_upcoming_idx ON event (place_id, duplicate_of_id, draft, end_date)');
        $this->addSql('DROP INDEX event_search_idx ON event');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX event_search_idx ON event (place_id, end_date, start_date)');
        $this->addSql('DROP INDEX event_place_upcoming_idx ON event');
    }
}
