<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200916160141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D649A0D96FBF ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649C05FB297 ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D64992FC23A8 ON user');
        $this->addSql('ALTER TABLE user DROP username_canonical, DROP email_canonical, DROP confirmation_token');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON user');
        $this->addSql('ALTER TABLE user ADD username_canonical VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, ADD email_canonical VARCHAR(180) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, ADD confirmation_token VARCHAR(180) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A0D96FBF ON user (email_canonical)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649C05FB297 ON user (confirmation_token)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64992FC23A8 ON user (username_canonical)');
    }
}
