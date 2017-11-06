<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170519204228 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_search2_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (place_id, date_fin, date_debut)');
        $this->addSql('CREATE INDEX agenda_search2_idx ON Agenda (place_id, date_debut)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_search2_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (site_id, date_fin, date_debut)');
        $this->addSql('CREATE INDEX agenda_search2_idx ON Agenda (site_id, date_debut)');
    }
}
