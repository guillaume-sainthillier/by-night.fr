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

final class Version20261005125231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the time the first session of the events starts, when their source gives one';
    }

    public function up(Schema $schema): void
    {
        // INSTANT: a nullable column at the end only changes the metadata of the 6 GB table
        $this->addSql('ALTER TABLE `event` ADD start_time TIME DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP COLUMN start_time, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
