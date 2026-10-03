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

final class Version20261003090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the counts of the events to come of each city and country by category and by agenda type in one table (recounted by app:events:count-upcoming), filled here so the portals have them until its first run';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE upcoming_count (events INT NOT NULL, agenda_type VARCHAR(15) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, city_id INT DEFAULT NULL, country_id VARCHAR(2) DEFAULT NULL, tag_id INT DEFAULT NULL, INDEX IDX_78E975ED8BAC62AF (city_id), INDEX IDX_78E975EDF92F3E70 (country_id), INDEX IDX_78E975EDBAD26311 (tag_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE upcoming_count ADD CONSTRAINT FK_78E975ED8BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE upcoming_count ADD CONSTRAINT FK_78E975EDF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE upcoming_count ADD CONSTRAINT FK_78E975EDBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO upcoming_count (tag_id, events, city_id, country_id) SELECT tag_id, events, city_id, country_id FROM upcoming_category');
        $this->addSql('DROP TABLE upcoming_category');

        // The same counts as UpcomingEventCounter, from the types app:events:classify-agenda-types stored on the events
        foreach (['concert', 'show', 'exhibition', 'family', 'student'] as $type) {
            foreach (['city_id', 'country_id'] as $zone) {
                $this->addSql(<<<SQL
                    INSERT INTO upcoming_count (agenda_type, events, {$zone})
                    SELECT '{$type}', COUNT(*), p.{$zone}
                    FROM event e
                    JOIN place p ON p.id = e.place_id
                    WHERE e.end_date >= CURRENT_DATE AND e.duplicate_of_id IS NULL AND e.draft = 0
                        AND FIND_IN_SET('{$type}', e.agenda_types) > 0 AND p.{$zone} IS NOT NULL
                    GROUP BY p.{$zone}
                    SQL);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE upcoming_category (events INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, tag_id INT NOT NULL, city_id INT DEFAULT NULL, country_id VARCHAR(2) DEFAULT NULL, INDEX IDX_CA1729FBAD26311 (tag_id), INDEX IDX_CA1729F8BAC62AF (city_id), INDEX IDX_CA1729FF92F3E70 (country_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE upcoming_category ADD CONSTRAINT FK_CA1729FBAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE upcoming_category ADD CONSTRAINT FK_CA1729F8BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE upcoming_category ADD CONSTRAINT FK_CA1729FF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO upcoming_category (tag_id, events, city_id, country_id) SELECT tag_id, events, city_id, country_id FROM upcoming_count WHERE tag_id IS NOT NULL');
        $this->addSql('DROP TABLE upcoming_count');
    }
}
