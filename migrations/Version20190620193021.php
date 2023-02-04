<?php

declare(strict_types=1);

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
final class Version20190620193021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2CEDC877F6BD1646');
        $this->addSql('ALTER TABLE Place DROP FOREIGN KEY FK_B5DC7CC9F6BD1646');
        $this->addSql('ALTER TABLE User DROP FOREIGN KEY FK_757B22AAF6BD1646');
        $this->addSql('DROP TABLE Site');
        $this->addSql('DROP INDEX IDX_2B41CD41F6BD1646 ON Agenda');
        $this->addSql('ALTER TABLE Agenda DROP site_id');
        $this->addSql('DROP INDEX IDX_B5DC7CC9F6BD1646 ON Place');
        $this->addSql('ALTER TABLE Place DROP site_id');
        $this->addSql('DROP INDEX IDX_2DA17977F6BD1646 ON User');
        $this->addSql('ALTER TABLE User DROP site_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql("CREATE TABLE Site (id INT AUTO_INCREMENT NOT NULL, subdomain VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, nom VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, twitter_id_widget VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci, twitter_url_widget VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, is_actif TINYINT(1) NOT NULL, distance_max DOUBLE PRECISION NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, INDEX recherche_site_idx (subdomain), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = '' ");
        $this->addSql('ALTER TABLE Agenda ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2CEDC877F6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('CREATE INDEX IDX_2B41CD41F6BD1646 ON Agenda (site_id)');
        $this->addSql('ALTER TABLE Place ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Place ADD CONSTRAINT FK_B5DC7CC9F6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('CREATE INDEX IDX_B5DC7CC9F6BD1646 ON Place (site_id)');
        $this->addSql('ALTER TABLE User ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE User ADD CONSTRAINT FK_757B22AAF6BD1646 FOREIGN KEY (site_id) REFERENCES Site (id)');
        $this->addSql('CREATE INDEX IDX_2DA17977F6BD1646 ON User (site_id)');
    }
}
