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

final class Version20261022154703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The alerts of the members\' outing changes: their choice, and what each member knows of their outings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD outing_alerts TINYINT DEFAULT 1 NOT NULL, ALGORITHM=INSTANT');
        $this->addSql('ALTER TABLE user_event ADD known_state JSON DEFAULT NULL, ADD alerted_at DATETIME DEFAULT NULL, ALGORITHM=INSTANT');
        // What the members going to the events to come know of them is the events as they are (OutingState::fromEvent()):
        // the first change after the release is told. A row left out would only take it in without telling it
        $this->addSql(<<<'SQL'
            UPDATE user_event ue
            INNER JOIN `event` e ON e.id = ue.event_id
            LEFT JOIN place p ON p.id = e.place_id
            SET ue.known_state = JSON_OBJECT(
                'start', DATE_FORMAT(e.start_date, '%Y-%m-%d'),
                'end', DATE_FORMAT(COALESCE(e.end_date, e.start_date), '%Y-%m-%d'),
                'time', TIME_FORMAT(e.start_time, '%H:%i'),
                'place', e.place_id,
                'venue', COALESCE(p.name, e.place_name),
                'city', COALESCE(p.city_name, e.place_city),
                'status', e.status
            )
            WHERE (ue.going = 1 OR ue.wish = 1) AND e.end_date >= CURDATE()
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_event DROP known_state, DROP alerted_at');
        $this->addSql('ALTER TABLE `user` DROP outing_alerts');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
