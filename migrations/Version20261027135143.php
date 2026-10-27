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
 * Event::$duplicates already deletes the rows redirecting to an event through the entity manager: the foreign key
 * now does the same for an event deleted in SQL, instead of turning its duplicates into pages of their own. The rows
 * already satisfy the new key, so it is added without checking them (in place, no copy of the event table).
 */
final class Version20261027135143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete the duplicates of an event deleted in SQL, and index the OAuth discriminator';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA7B0974BFB');
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA72CC33300 FOREIGN KEY (duplicate_of_id) REFERENCES `event` (id) ON DELETE CASCADE, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 1');
        $this->addSql('CREATE INDEX discr_idx ON oauth (discr) ALGORITHM=INPLACE LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX discr_idx ON oauth');
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA72CC33300');
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA7B0974BFB FOREIGN KEY (duplicate_of_id) REFERENCES `event` (id) ON DELETE SET NULL, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 1');
    }
}
