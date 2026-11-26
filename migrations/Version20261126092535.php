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
 * Members follow publishers and venues (follow) and hear by e-mail of their new events: when an event went online
 * (event.published_at), and until when a member heard of them (user.follow_alerts_sent_at). The columns are added in
 * place (INSTANT), without copying the event table.
 *
 * The events online so far, and those their source removed (EventStatus::Removed, online until then), are given their
 * creation as their publication: news to nobody, and an empty published_at only means never online. Filled by ranges
 * of ids, each committed on its own, so that the imports are never held long by a lock on the table.
 */
final class Version20261126092535 extends AbstractMigration
{
    private const int BATCH_SIZE = 100_000;

    public function getDescription(): string
    {
        return 'Let members follow publishers and venues, and record when an event went online';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE follow (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, publisher_id INT DEFAULT NULL, place_id INT DEFAULT NULL, UNIQUE INDEX follow_publisher_unique (user_id, publisher_id), UNIQUE INDEX follow_place_unique (user_id, place_id), INDEX IDX_68344470A76ED395 (user_id), INDEX IDX_6834447040C86FCE (publisher_id), INDEX IDX_68344470DA6A219 (place_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_6834447040C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE follow ADD CONSTRAINT FK_68344470DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD follow_alerts TINYINT DEFAULT 1 NOT NULL, ADD follow_alerts_sent_at DATETIME DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('ALTER TABLE event ADD published_at DATETIME DEFAULT NULL, ALGORITHM=INSTANT');

        $maxId = (int) $this->connection->fetchOne('SELECT MAX(id) FROM event');
        for ($from = 1; $from <= $maxId; $from += self::BATCH_SIZE) {
            $this->addSql(
                'UPDATE event SET published_at = created_at WHERE id BETWEEN :from AND :to AND published_at IS NULL AND (draft = 0 OR status = :removed)',
                ['from' => $from, 'to' => $from + self::BATCH_SIZE - 1, 'removed' => 'removed'],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP published_at');
        $this->addSql('ALTER TABLE `user` DROP follow_alerts, DROP follow_alerts_sent_at');
        $this->addSql('DROP TABLE follow');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
