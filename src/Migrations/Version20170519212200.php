<?php

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170519212200 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_slug_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_nom_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_date_debut_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_fb_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_search2_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (date_fin, date_debut, place_id)');
        $this->addSql('DROP INDEX agenda_participations ON Agenda');
        $this->addSql('CREATE INDEX agenda_fb_participations ON Agenda (fb_participations, fb_interets)');
    }

    /**
     * @param Schema $schema
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Migrations\AbortMigrationException
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_slug_idx ON Agenda (slug)');
        $this->addSql('CREATE INDEX agenda_nom_idx ON Agenda (nom)');
        $this->addSql('CREATE INDEX agenda_date_debut_idx ON Agenda (date_debut)');
        $this->addSql('CREATE INDEX agenda_fb_idx ON Agenda (facebook_event_id)');
        $this->addSql('CREATE INDEX agenda_search2_idx ON Agenda (place_id, date_debut)');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (place_id, date_fin, date_debut)');
        $this->addSql('DROP INDEX agenda_fb_participations ON Agenda');
        $this->addSql('CREATE INDEX agenda_participations ON Agenda (fb_participations, fb_interets)');
    }
}
