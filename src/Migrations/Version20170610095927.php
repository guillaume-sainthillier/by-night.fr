<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Migrations\AbortMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170610095927 extends AbstractMigration
{
    /**
     * @param Schema $schema
     *
     * @throws DBALException
     * @throws AbortMigrationException
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE Ville');
        $this->addSql('ALTER TABLE HistoriqueMaj DROP FOREIGN KEY FK_E295E8BAF6BD1646');
        $this->addSql('DROP INDEX IDX_E295E8BAF6BD1646 ON HistoriqueMaj');
        $this->addSql('ALTER TABLE HistoriqueMaj DROP site_id');
        $this->addSql('ALTER TABLE User ADD city_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE User ADD CONSTRAINT FK_2DA179778BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id)');
        $this->addSql('CREATE INDEX IDX_2DA179778BAC62AF ON User (city_id)');
    }

    /**
     * @param Schema $schema
     *
     * @throws DBALException
     * @throws AbortMigrationException
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE Ville (id INT AUTO_INCREMENT NOT NULL, site_id INT NOT NULL, nom VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, code_postal VARCHAR(10) DEFAULT NULL COLLATE utf8_unicode_ci, slug VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, path VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, url VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, INDEX IDX_8202F6C7F6BD1646 (site_id), INDEX ville_nom_idx (nom), INDEX ville_slug_idx (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE Ville ADD CONSTRAINT FK_8202F6C7F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE HistoriqueMaj ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE HistoriqueMaj ADD CONSTRAINT FK_E295E8BAF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('CREATE INDEX IDX_E295E8BAF6BD1646 ON HistoriqueMaj (site_id)');
        $this->addSql('ALTER TABLE User DROP FOREIGN KEY FK_2DA179778BAC62AF');
        $this->addSql('DROP INDEX IDX_2DA179778BAC62AF ON User');
        $this->addSql('ALTER TABLE User DROP city_id');
    }
}
