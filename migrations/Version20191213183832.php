<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191213183832 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD parser_version VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE Exploration ADD parser_version VARCHAR(7) DEFAULT NULL');
        $this->addSql('UPDATE Agenda SET parser_version = \'1.0\' WHERE external_id IS NOT NULL');
        $this->addSql('UPDATE Exploration SET parser_version = \'1.0\'');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP parser_version');
        $this->addSql('ALTER TABLE Exploration DROP parser_version');
    }
}
