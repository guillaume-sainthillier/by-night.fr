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

final class Version20260925144450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the event indexes no query reads any more (legacy category and theme, type, top soirées)';
    }

    public function up(Schema $schema): void
    {
        // Dropping an index only changes the table's metadata in MySQL 8: no copy, no lock held
        $this->addSql('DROP INDEX event_category_idx ON event');
        $this->addSql('DROP INDEX event_theme_idx ON event');
        $this->addSql('DROP INDEX event_top_soiree_idx ON event');
        $this->addSql('DROP INDEX event_type_idx ON event');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX event_category_idx ON event (category)');
        $this->addSql('CREATE INDEX event_theme_idx ON event (theme)');
        $this->addSql('CREATE INDEX event_top_soiree_idx ON event (end_date, participations)');
        $this->addSql('CREATE INDEX event_type_idx ON event (type)');
    }
}
