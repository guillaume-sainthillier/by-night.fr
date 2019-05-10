<?php

namespace DoctrineMigrations;


use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161205113345 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX UNIQ_2A9385649BE8FD98 ON Exploration');
        $this->addSql('ALTER TABLE Exploration DROP facebook_id, CHANGE id id VARCHAR(31) NOT NULL');
        $this->addSql('ALTER TABLE User DROP locked, DROP expires_at, DROP credentials_expire_at, CHANGE salt salt VARCHAR(255) DEFAULT NULL');
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Exploration ADD facebook_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2A9385649BE8FD98 ON Exploration (facebook_id)');
        $this->addSql('ALTER TABLE User ADD locked TINYINT(1) NOT NULL, ADD expires_at DATETIME DEFAULT NULL, ADD credentials_expire_at DATETIME DEFAULT NULL, CHANGE salt salt VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
    }
}
