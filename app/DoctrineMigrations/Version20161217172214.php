<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161217172214 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD system_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE User ADD system_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE Agenda SET system_path = path WHERE user_id IS NULL');
        $this->addSql('UPDATE Agenda SET path = NULL WHERE user_id IS NULL');
        $this->addSql('UPDATE User SET system_path = path WHERE salt IS NULL');
        $this->addSql('UPDATE User SET path = NULL WHERE salt IS NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE User SET path = system_path WHERE salt IS NULL');
        $this->addSql('UPDATE Agenda SET path = system_path WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE Agenda DROP system_path');
        $this->addSql('ALTER TABLE User DROP system_path');
    }
}
