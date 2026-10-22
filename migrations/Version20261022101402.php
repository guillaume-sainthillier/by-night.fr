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

final class Version20261022101402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The comment count of the events, and an index for the other events of the same day';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` ADD comment_count INT DEFAULT 0 NOT NULL, ALGORITHM=INSTANT');
        $this->addSql('UPDATE `event` e INNER JOIN (SELECT event_id, COUNT(*) AS comments FROM comment WHERE parent_id IS NULL AND approved = 1 GROUP BY event_id) c ON c.event_id = e.id SET e.comment_count = c.comments');
        // Built while the site keeps writing events
        $this->addSql('CREATE INDEX event_similar_idx ON `event` (start_date, draft, duplicate_of_id, place_id) ALGORITHM=INPLACE LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX event_similar_idx ON `event`');
        $this->addSql('ALTER TABLE `event` DROP comment_count');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
