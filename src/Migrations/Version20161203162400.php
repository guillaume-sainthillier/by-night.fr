<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Migrations\AbortMigrationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161203162400 extends AbstractMigration
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
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Exploration DROP INDEX exploration_facebook_id_site_idx, ADD UNIQUE INDEX UNIQ_2A9385649BE8FD98 (facebook_id)');
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
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Exploration DROP INDEX UNIQ_2A9385649BE8FD98, ADD INDEX exploration_facebook_id_site_idx (facebook_id)');
    }
}
