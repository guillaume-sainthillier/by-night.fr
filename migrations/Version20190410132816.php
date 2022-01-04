<?php

declare(strict_types=1);

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
final class Version20190410132816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41309D0B4F');
        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD418BAC62AF');
        $this->addSql('DROP INDEX IDX_2B41CD41309D0B4F ON Agenda');
        $this->addSql('DROP INDEX IDX_2B41CD418BAC62AF ON Agenda');
        $this->addSql('ALTER TABLE Agenda DROP city_id, DROP zip_city_id');
        $this->addSql('ALTER TABLE Place DROP FOREIGN KEY FK_B5DC7CC9309D0B4F');
        $this->addSql('DROP INDEX IDX_B5DC7CC9309D0B4F ON Place');
        $this->addSql('ALTER TABLE Place DROP zip_city_id');
        $this->addSql('ALTER TABLE country ADD postal_code_regex VARCHAR(511) DEFAULT NULL');
        $this->addSql('ALTER TABLE zip_city ADD admin1_name VARCHAR(100) DEFAULT NULL, ADD admin2_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD city_id INT DEFAULT NULL, ADD zip_city_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41309D0B4F FOREIGN KEY (zip_city_id) REFERENCES zip_city (id)');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD418BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id)');
        $this->addSql('CREATE INDEX IDX_2B41CD41309D0B4F ON Agenda (zip_city_id)');
        $this->addSql('CREATE INDEX IDX_2B41CD418BAC62AF ON Agenda (city_id)');
        $this->addSql('ALTER TABLE Place ADD zip_city_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Place ADD CONSTRAINT FK_B5DC7CC9309D0B4F FOREIGN KEY (zip_city_id) REFERENCES zip_city (id)');
        $this->addSql('CREATE INDEX IDX_B5DC7CC9309D0B4F ON Place (zip_city_id)');
        $this->addSql('ALTER TABLE country DROP postal_code_regex');
        $this->addSql('ALTER TABLE zip_city DROP admin1_name, DROP admin2_name');
    }
}
