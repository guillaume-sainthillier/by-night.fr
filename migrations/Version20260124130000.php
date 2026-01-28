<?php

declare(strict_types=1);

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

final class Version20260124130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duplicate_of_id column to event table for event deduplication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` ADD duplicate_of_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA7B0974BFB FOREIGN KEY (duplicate_of_id) REFERENCES `event` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX event_duplicate_of_idx ON `event` (duplicate_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA7B0974BFB');
        $this->addSql('DROP INDEX event_duplicate_of_idx ON `event`');
        $this->addSql('ALTER TABLE `event` DROP duplicate_of_id');
    }
}
