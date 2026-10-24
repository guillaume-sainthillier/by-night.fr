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

/**
 * The outing alerts leave in batches, a few times a day (app:outing-alerts:send): a change of an event's dates, venue
 * or status flags it (Event::$flaggedForAlertAt) instead of queuing a delayed CheckOutingChanges message.
 */
final class Version20261024075052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag the events whose followers are still to hear of a change, for the batched outing alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` ADD flagged_for_alert_at DATETIME DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('CREATE INDEX event_flagged_for_alert_at_idx ON `event` (flagged_for_alert_at) ALGORITHM=INPLACE LOCK=NONE');
        // The CheckOutingChanges messages still queued (the changes of the last 15 minutes, those held back until the
        // next morning) are no longer read: every event to come with followers is flagged, and the first run tells each
        // follower what they do not know yet, nothing more. A day back, so that the first run takes them whatever the
        // time zone of the server
        $this->addSql(<<<'SQL'
            UPDATE `event` e
            INNER JOIN (SELECT DISTINCT event_id FROM user_event WHERE going = 1 OR wish = 1) followed ON followed.event_id = e.id
            SET e.flagged_for_alert_at = NOW() - INTERVAL 1 DAY
            WHERE e.end_date >= CURDATE() AND e.duplicate_of_id IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX event_flagged_for_alert_at_idx ON `event`');
        $this->addSql('ALTER TABLE `event` DROP flagged_for_alert_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
