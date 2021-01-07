<?php

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
class Version20160531195953 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Image DROP FOREIGN KEY FK_4FC2B5BF6BD1646');
        $this->addSql('DROP INDEX IDX_4FC2B5BF6BD1646 ON Image');
        $this->addSql('ALTER TABLE Image DROP site_id');
        $this->addSql('ALTER TABLE User ADD image_id INT DEFAULT NULL, CHANGE show_socials show_socials TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE User ADD CONSTRAINT FK_2DA179773DA5256D FOREIGN KEY (image_id) REFERENCES Image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA179773DA5256D ON User (image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Image ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Image ADD CONSTRAINT FK_4FC2B5BF6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('CREATE INDEX IDX_4FC2B5BF6BD1646 ON Image (site_id)');
        $this->addSql('ALTER TABLE User DROP FOREIGN KEY FK_2DA179773DA5256D');
        $this->addSql('DROP INDEX UNIQ_2DA179773DA5256D ON User');
        $this->addSql('ALTER TABLE User DROP image_id, CHANGE show_socials show_socials TINYINT(1) DEFAULT \'1\'');
    }
}
