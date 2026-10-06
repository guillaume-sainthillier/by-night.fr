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

final class Version20261006101529 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the member of its family whose picture an event shows';
    }

    public function up(Schema $schema): void
    {
        // Nothing rebuilds the 6 GB event table: an INSTANT column, an index built in place while it stays writable,
        // and the foreign key added in place, which MySQL only allows with the checks off (the column is all NULL)
        $this->addSql('ALTER TABLE `event` ADD picture_from_id INT DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('CREATE INDEX IDX_3BAE0AA765E82DD9 ON `event` (picture_from_id) ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA765E82DD9 FOREIGN KEY (picture_from_id) REFERENCES `event` (id) ON DELETE SET NULL, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('SET foreign_key_checks = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA765E82DD9');
        $this->addSql('DROP INDEX IDX_3BAE0AA765E82DD9 ON `event`');
        $this->addSql('ALTER TABLE `event` DROP COLUMN picture_from_id, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
