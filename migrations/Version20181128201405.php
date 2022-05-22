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
final class Version20181128201405 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda DROP lieu_nom, DROP code_postal, DROP commune, DROP station_metro_tram, DROP tranche_age, DROP ville, DROP rue');
        $this->addSql('DROP INDEX user_nom_idx ON User');
        $this->addSql('ALTER TABLE User DROP nom');
        $this->addSql('ALTER TABLE location ADD `name` VARCHAR(255) NOT NULL, CHANGE id id VARCHAR(32) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), "Migration can only be executed safely on 'mysql'.");

        $this->addSql('ALTER TABLE Agenda ADD lieu_nom VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, ADD code_postal VARCHAR(15) DEFAULT NULL COLLATE utf8_unicode_ci, ADD commune VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, ADD station_metro_tram VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, ADD tranche_age VARCHAR(128) DEFAULT NULL COLLATE utf8_unicode_ci, ADD ville VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, ADD rue VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE User ADD nom VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('CREATE INDEX user_nom_idx ON User (nom)');
        $this->addSql('ALTER TABLE location DROP `name`, CHANGE id id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
