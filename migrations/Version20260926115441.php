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

final class Version20260926115441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the number of events to come on places, cities and countries (recounted by app:events:count-upcoming), filled here so the portals are not empty until its first run';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone ADD upcoming_events INT DEFAULT 0');
        $this->addSql('CREATE INDEX admin_zone_type_upcoming_idx ON admin_zone (type, upcoming_events, population)');
        $this->addSql('CREATE INDEX admin_zone_type_country_upcoming_idx ON admin_zone (type, country_id, upcoming_events, population)');
        $this->addSql('ALTER TABLE place ADD upcoming_events INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX place_upcoming_idx ON place (upcoming_events)');
        $this->addSql('CREATE INDEX place_city_upcoming_idx ON place (city_id, upcoming_events)');
        $this->addSql('ALTER TABLE country ADD upcoming_events INT DEFAULT 0 NOT NULL');

        // The same counts as UpcomingEventCounter: the places from the events, the cities and countries from the places
        $this->addSql(<<<'SQL'
            UPDATE place p
            JOIN (
                SELECT place_id, COUNT(*) AS events FROM event
                WHERE end_date >= CURRENT_DATE AND duplicate_of_id IS NULL AND draft = 0
                GROUP BY place_id
            ) upcoming ON upcoming.place_id = p.id
            SET p.upcoming_events = upcoming.events
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE admin_zone c
            JOIN (SELECT city_id, SUM(upcoming_events) AS events FROM place WHERE upcoming_events > 0 GROUP BY city_id) upcoming ON upcoming.city_id = c.id
            SET c.upcoming_events = upcoming.events
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE country c
            JOIN (SELECT country_id, SUM(upcoming_events) AS events FROM place WHERE upcoming_events > 0 GROUP BY country_id) upcoming ON upcoming.country_id = c.id
            SET c.upcoming_events = upcoming.events
            SQL);
        // The indexes were built while every count was 0, and filling them changes too few rows for InnoDB to
        // recompute its statistics on its own
        $this->addSql('ANALYZE TABLE admin_zone, place, country');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX admin_zone_type_upcoming_idx ON admin_zone');
        $this->addSql('DROP INDEX admin_zone_type_country_upcoming_idx ON admin_zone');
        $this->addSql('ALTER TABLE admin_zone DROP upcoming_events');
        $this->addSql('DROP INDEX place_upcoming_idx ON place');
        $this->addSql('DROP INDEX place_city_upcoming_idx ON place');
        $this->addSql('ALTER TABLE place DROP upcoming_events');
        $this->addSql('ALTER TABLE country DROP upcoming_events');
    }
}
