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

final class Version20261005124635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the ticketing link of the events, which the sources tell apart from their other links';
    }

    public function up(Schema $schema): void
    {
        // INSTANT: a nullable column at the end only changes the metadata of the 6 GB table
        $this->addSql('ALTER TABLE `event` ADD ticket_url VARCHAR(1024) DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP COLUMN ticket_url, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
