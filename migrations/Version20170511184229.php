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
class Version20170511184229 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE admin_zone (id INT NOT NULL, country_id VARCHAR(2) DEFAULT NULL, parent_id INT DEFAULT NULL, slug VARCHAR(200) NOT NULL, name VARCHAR(200) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, population INT NOT NULL, admin1_code VARCHAR(20) NOT NULL, admin2_code VARCHAR(80) NOT NULL, type VARCHAR(10) NOT NULL, UNIQUE INDEX UNIQ_80F242E7989D9B62 (slug), INDEX IDX_80F242E7F92F3E70 (country_id), INDEX IDX_80F242E7727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE country (id VARCHAR(2) NOT NULL, slug VARCHAR(63) NOT NULL, locale VARCHAR(5) DEFAULT NULL, name VARCHAR(63) NOT NULL, capital VARCHAR(63) NOT NULL, UNIQUE INDEX UNIQ_5373C966989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE zip_city (id INT AUTO_INCREMENT NOT NULL, country_id VARCHAR(2) DEFAULT NULL, postal_code VARCHAR(20) NOT NULL, name VARCHAR(180) NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, admin1_code VARCHAR(20) NOT NULL, admin2_code VARCHAR(80) NOT NULL, INDEX IDX_FBE2D3F4F92F3E70 (country_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_zone ADD CONSTRAINT FK_80F242E7F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE admin_zone ADD CONSTRAINT FK_80F242E7727ACA70 FOREIGN KEY (parent_id) REFERENCES admin_zone (id)');
        $this->addSql('ALTER TABLE zip_city ADD CONSTRAINT FK_FBE2D3F4F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE admin_zone DROP FOREIGN KEY FK_80F242E7727ACA70');
        $this->addSql('ALTER TABLE admin_zone DROP FOREIGN KEY FK_80F242E7F92F3E70');
        $this->addSql('ALTER TABLE zip_city DROP FOREIGN KEY FK_FBE2D3F4F92F3E70');
        $this->addSql('DROP TABLE admin_zone');
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE zip_city');
    }
}
