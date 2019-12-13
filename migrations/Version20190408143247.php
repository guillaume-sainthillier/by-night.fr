<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
final class Version20190408143247 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD city_id INT DEFAULT NULL, ADD zip_city_id INT DEFAULT NULL, ADD country_id VARCHAR(2) DEFAULT NULL, ADD place_name VARCHAR(255) NOT NULL, ADD place_street VARCHAR(127) DEFAULT NULL, ADD place_city VARCHAR(127) DEFAULT NULL, ADD place_postal_code VARCHAR(7) DEFAULT NULL, ADD place_external_id VARCHAR(127) DEFAULT NULL, ADD place_facebook_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD418BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id)');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41309D0B4F FOREIGN KEY (zip_city_id) REFERENCES zip_city (id)');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id)');
        $this->addSql('CREATE INDEX IDX_2B41CD418BAC62AF ON Agenda (city_id)');
        $this->addSql('CREATE INDEX IDX_2B41CD41309D0B4F ON Agenda (zip_city_id)');
        $this->addSql('CREATE INDEX IDX_2B41CD41F92F3E70 ON Agenda (country_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD418BAC62AF');
        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41309D0B4F');
        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41F92F3E70');
        $this->addSql('DROP INDEX IDX_2B41CD418BAC62AF ON Agenda');
        $this->addSql('DROP INDEX IDX_2B41CD41309D0B4F ON Agenda');
        $this->addSql('DROP INDEX IDX_2B41CD41F92F3E70 ON Agenda');
        $this->addSql('ALTER TABLE Agenda DROP city_id, DROP zip_city_id, DROP country_id, DROP place_name, DROP place_street, DROP place_city, DROP place_postal_code, DROP place_external_id, DROP place_facebook_id');
    }
}
