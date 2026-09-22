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

final class Version20260922163521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Delete the password reset requests along with their user, so a pending request no longer blocks the deletion of an account';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY `FK_7CE748AA76ED395`');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY `FK_7CE748AA76ED395`');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }
}
