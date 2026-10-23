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

final class Version20261022153816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The members\' consent to the weekend selection by e-mail, and the last week it was sent to them';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD weekend_digest TINYINT DEFAULT 0 NOT NULL, ADD weekend_digest_since DATETIME DEFAULT NULL, ADD weekend_digest_sent_week VARCHAR(8) DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP weekend_digest, DROP weekend_digest_since, DROP weekend_digest_sent_week');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
