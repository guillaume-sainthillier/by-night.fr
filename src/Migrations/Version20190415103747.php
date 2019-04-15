<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190415103747 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda ADD place_country_id VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41D7EFA878 FOREIGN KEY (place_country_id) REFERENCES country (id)');
        $this->addSql('CREATE INDEX IDX_2B41CD41D7EFA878 ON Agenda (place_country_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41D7EFA878');
        $this->addSql('DROP INDEX IDX_2B41CD41D7EFA878 ON Agenda');
        $this->addSql('ALTER TABLE Agenda DROP place_country_id');
    }
}
