<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160704163341 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_2DA179773DA5256D');
        $this->addSql('DROP INDEX UNIQ_2DA179773DA5256D ON user');
        $this->addSql('ALTER TABLE user ADD path VARCHAR(255) NOT NULL, ADD updated_at DATETIME NOT NULL, DROP image_id');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE User ADD image_id INT DEFAULT NULL, DROP path, DROP updated_at');
        $this->addSql('ALTER TABLE User ADD CONSTRAINT FK_2DA179773DA5256D FOREIGN KEY (image_id) REFERENCES image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA179773DA5256D ON User (image_id)');
    }
}
