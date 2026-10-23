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

final class Version20261022154022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The secret address of the members\' outings as a calendar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD calendar_token VARCHAR(32) DEFAULT NULL, ALGORITHM=INSTANT');
        // Every row is NULL until its member opens "Mes sorties": built while the site keeps writing members
        $this->addSql('CREATE UNIQUE INDEX user_calendar_token_unique ON `user` (calendar_token) ALGORITHM=INPLACE LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX user_calendar_token_unique ON `user`');
        $this->addSql('ALTER TABLE `user` DROP calendar_token');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
