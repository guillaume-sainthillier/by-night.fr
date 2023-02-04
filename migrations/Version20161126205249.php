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
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161126205249 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('DROP TABLE IF EXISTS Image');
        $this->addSql('DROP INDEX IDX_2A938564F6BD1646 ON Exploration');
        $this->addSql('DROP INDEX exploration_facebook_id_site_idx ON Exploration');
        $this->addSql('ALTER TABLE Exploration DROP site_id');
        $this->addSql('CREATE INDEX exploration_facebook_id_site_idx ON Exploration (facebook_id)');
        $this->addSql('ALTER TABLE User DROP expired, DROP credentials_expired, CHANGE username username VARCHAR(180) NOT NULL, CHANGE username_canonical username_canonical VARCHAR(180) NOT NULL, CHANGE email email VARCHAR(180) NOT NULL, CHANGE email_canonical email_canonical VARCHAR(180) NOT NULL, CHANGE confirmation_token confirmation_token VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA17977C05FB297 ON User (confirmation_token)');
        $this->addSql('DELETE FROM Exploration');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('CREATE TABLE Image (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('DROP INDEX exploration_facebook_id_site_idx ON Exploration');
        $this->addSql('ALTER TABLE Exploration ADD site_id INT NOT NULL');
        $this->addSql('ALTER TABLE Exploration ADD CONSTRAINT FK_AC0F0AB3F6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('CREATE INDEX IDX_2A938564F6BD1646 ON Exploration (site_id)');
        $this->addSql('CREATE INDEX exploration_facebook_id_site_idx ON Exploration (facebook_id, site_id)');
        $this->addSql('DROP INDEX UNIQ_2DA17977C05FB297 ON User');
        $this->addSql('ALTER TABLE User ADD expired TINYINT(1) NOT NULL, ADD credentials_expired TINYINT(1) NOT NULL, CHANGE username username VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE username_canonical username_canonical VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE email email VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE email_canonical email_canonical VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE confirmation_token confirmation_token VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
