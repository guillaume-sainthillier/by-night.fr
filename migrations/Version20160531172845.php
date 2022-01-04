<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
class Version20160531172845 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE BlackList');
        $this->addSql('ALTER TABLE User ADD show_socials TINYINT(1) DEFAULT 1, ADD website VARCHAR(255) DEFAULT NULL');

        $this->addSql('UPDATE User SET from_login = 1 WHERE CHAR_LENGTH(password) > 25');
        $this->addSql('UPDATE User SET from_login = 0 WHERE CHAR_LENGTH(password) <= 25');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE BlackList (id INT AUTO_INCREMENT NOT NULL, site_id INT NOT NULL, facebook_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, reason VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, INDEX IDX_19D47E18F6BD1646 (site_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE BlackList ADD CONSTRAINT FK_19D47E18F6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('ALTER TABLE User DROP show_socials, DROP website');
    }
}
