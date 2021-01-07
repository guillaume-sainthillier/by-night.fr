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
final class Version20191120190710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Comment CHANGE date_creation created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE date_modification updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE Place ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE Calendrier CHANGE last_date created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP TABLE news');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE news (id INT AUTO_INCREMENT NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL, numero_edition INT NOT NULL, wordpress_post_id INT NOT NULL, tweet_post_id VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, fb_post_id VARCHAR(256) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, UNIQUE INDEX UNIQ_1DD39950DC7C6711 (wordpress_post_id), UNIQUE INDEX UNIQ_1DD399506EC506BE (numero_edition), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE Calendrier ADD last_date DATETIME NOT NULL, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE Comment ADD date_creation DATETIME NOT NULL, ADD date_modification DATETIME NOT NULL, DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE Place DROP created_at, DROP updated_at');
    }
}
