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

final class Version20261006094301 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the links between the events of two sources found to be the same show, and why a duplicate redirects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cross_source_link (created_at DATETIME NOT NULL, kept_apart TINYINT DEFAULT 0 NOT NULL, id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, linked_event_id INT NOT NULL, INDEX cross_source_link_linked_event_idx (linked_event_id), UNIQUE INDEX cross_source_link_pair_unique (event_id, linked_event_id), INDEX IDX_249E038471F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE cross_source_link ADD CONSTRAINT FK_249E038471F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cross_source_link ADD CONSTRAINT FK_249E03841FF7A654 FOREIGN KEY (linked_event_id) REFERENCES `event` (id) ON DELETE CASCADE');

        // INSTANT: a nullable column at the end only changes the metadata of the event table. The links already made
        // keep no reason: the resolver still tells a family by its identity hash
        $this->addSql('ALTER TABLE `event` ADD duplicate_reason VARCHAR(16) DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP COLUMN duplicate_reason, ALGORITHM=INSTANT');
        $this->addSql('DROP TABLE cross_source_link');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
