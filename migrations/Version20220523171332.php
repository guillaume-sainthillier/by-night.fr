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
final class Version20220523171332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment CHANGE commentaire comment LONGTEXT NOT NULL, CHANGE approuve approved TINYINT(1) NOT NULL');
        $this->addSql('DROP INDEX event_categorie_manifestation_idx ON event');
        $this->addSql('DROP INDEX event_date_debut_idx ON event');
        $this->addSql('DROP INDEX event_theme_manifestation_idx ON event');
        $this->addSql('DROP INDEX event_type_manifestation_idx ON event');
        $this->addSql('DROP INDEX event_search_idx ON event');
        $this->addSql('DROP INDEX event_top_soiree_idx ON event');
        $this->addSql('ALTER TABLE event CHANGE date_debut start_date DATE DEFAULT NULL, CHANGE date_fin end_date DATE DEFAULT NULL, CHANGE adresse address VARCHAR(255) DEFAULT NULL, CHANGE type_manifestation `type` VARCHAR(128) DEFAULT NULL, CHANGE categorie_manifestation category VARCHAR(128) DEFAULT NULL, CHANGE theme_manifestation theme VARCHAR(128) DEFAULT NULL, CHANGE tarif prices VARCHAR(255) DEFAULT NULL, CHANGE modification_derniere_minute status VARCHAR(16) DEFAULT NULL, CHANGE brouillon draft TINYINT(1) NOT NULL');
        $this->addSql('CREATE INDEX event_start_date_idx ON event (start_date)');
        $this->addSql('CREATE INDEX event_theme_idx ON event (theme)');
        $this->addSql('CREATE INDEX event_type_idx ON event (type)');
        $this->addSql('CREATE INDEX event_category_idx ON event (category)');
        $this->addSql('CREATE INDEX event_search_idx ON event (place_id, end_date, start_date)');
        $this->addSql('CREATE INDEX event_top_soiree_idx ON event (end_date, participations)');
        $this->addSql('DROP INDEX place_nom_idx ON place');
        $this->addSql('ALTER TABLE place CHANGE ville city_name VARCHAR(127) DEFAULT NULL, CHANGE rue street VARCHAR(127) DEFAULT NULL, CHANGE code_postal city_postal_code VARCHAR(7) DEFAULT NULL, CHANGE nom name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX place_name_idx ON place (name)');
        $this->addSql('ALTER TABLE `user` CHANGE is_verified verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user_event CHANGE participe going TINYINT(1) NOT NULL, CHANGE interet wish TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE comment CHANGE comment commentaire LONGTEXT NOT NULL, CHANGE approved approuve TINYINT(1) NOT NULL');
        $this->addSql('DROP INDEX event_start_date_idx ON event');
        $this->addSql('DROP INDEX event_theme_idx ON event');
        $this->addSql('DROP INDEX event_type_idx ON event');
        $this->addSql('DROP INDEX event_category_idx ON event');
        $this->addSql('DROP INDEX event_search_idx ON event');
        $this->addSql('DROP INDEX event_top_soiree_idx ON event');
        $this->addSql('ALTER TABLE event CHANGE start_date date_debut DATE DEFAULT NULL, CHANGE end_date date_fin DATE DEFAULT NULL, CHANGE address adresse VARCHAR(255) DEFAULT NULL, CHANGE `type` type_manifestation VARCHAR(128) DEFAULT NULL, CHANGE category categorie_manifestation VARCHAR(128) DEFAULT NULL, CHANGE theme theme_manifestation VARCHAR(128) DEFAULT NULL, CHANGE prices tarif VARCHAR(255) DEFAULT NULL, CHANGE status modification_derniere_minute VARCHAR(16) DEFAULT NULL, CHANGE draft brouillon TINYINT(1) NOT NULL');
        $this->addSql('CREATE INDEX event_categorie_manifestation_idx ON event (categorie_manifestation)');
        $this->addSql('CREATE INDEX event_date_debut_idx ON event (date_debut)');
        $this->addSql('CREATE INDEX event_theme_manifestation_idx ON event (theme_manifestation)');
        $this->addSql('CREATE INDEX event_type_manifestation_idx ON event (type_manifestation)');
        $this->addSql('CREATE INDEX event_search_idx ON event (place_id, date_fin, date_debut)');
        $this->addSql('CREATE INDEX event_top_soiree_idx ON event (date_fin, participations)');
        $this->addSql('DROP INDEX place_name_idx ON place');
        $this->addSql('ALTER TABLE place CHANGE city_name ville VARCHAR(127) DEFAULT NULL, CHANGE street rue VARCHAR(127) DEFAULT NULL, CHANGE city_postal_code code_postal VARCHAR(7) DEFAULT NULL, CHANGE name nom VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX place_nom_idx ON place (nom)');
        $this->addSql('ALTER TABLE user CHANGE verified is_verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user_event CHANGE going participe TINYINT(1) NOT NULL, CHANGE wish interet TINYINT(1) NOT NULL');
    }
}
