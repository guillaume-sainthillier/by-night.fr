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

final class Version20261005100936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Widen event.status_message to 2000 characters: the Sowprog API v2 gives whole cancellation announcements (up to 924 characters)';
    }

    public function up(Schema $schema): void
    {
        // Online: utf8mb4 VARCHAR(255) already stores its length on 2 bytes, so widening it is a metadata change
        $this->addSql('ALTER TABLE `event` MODIFY status_message VARCHAR(2000) DEFAULT NULL, ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        // Copies the table, and fails on a message longer than 255 characters
        $this->addSql('ALTER TABLE `event` MODIFY status_message VARCHAR(255) DEFAULT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
