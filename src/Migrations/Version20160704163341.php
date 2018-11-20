<?php

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160704163341 extends AbstractMigration
{
    /**
     * @param Schema $schema
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE User DROP FOREIGN KEY FK_2DA179773DA5256D');
        $this->addSql('DROP INDEX UNIQ_2DA179773DA5256D ON User');
        $this->addSql('ALTER TABLE User ADD path VARCHAR(255) NOT NULL, ADD updated_at DATETIME NOT NULL, DROP image_id');
    }

    /**
     * @param Schema $schema
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE User ADD image_id INT DEFAULT NULL, DROP path, DROP updated_at');
        $this->addSql('ALTER TABLE User ADD CONSTRAINT FK_2DA179773DA5256D FOREIGN KEY (image_id) REFERENCES Image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA179773DA5256D ON User (image_id)');
    }
}
