<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191213161354 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DELETE e FROM Exploration e JOIN Exploration e2 ON e2.external_id = e.external_id AND e2.id <> e.id WHERE e.id > e2.id');
        $this->addSql('ALTER TABLE Exploration DROP INDEX exploration_external_id_idx, ADD UNIQUE INDEX UNIQ_2A9385649F75D7B0 (external_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Exploration DROP INDEX UNIQ_2A9385649F75D7B0, ADD INDEX exploration_external_id_idx (external_id)');
    }
}
