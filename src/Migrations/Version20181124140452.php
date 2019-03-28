<?php declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181124140452 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_slug_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_nom_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_date_debut_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_fb_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_search2_idx ON Agenda');
        $this->addSql('DROP INDEX agenda_participations ON Agenda');
        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_fb_participations ON Agenda (date_fin, fb_participations, fb_interets)');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (place_id, date_fin, date_debut)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX agenda_fb_participations ON Agenda');
        $this->addSql('DROP INDEX agenda_search_idx ON Agenda');
        $this->addSql('CREATE INDEX agenda_slug_idx ON Agenda (slug)');
        $this->addSql('CREATE INDEX agenda_nom_idx ON Agenda (nom)');
        $this->addSql('CREATE INDEX agenda_date_debut_idx ON Agenda (date_debut)');
        $this->addSql('CREATE INDEX agenda_fb_idx ON Agenda (facebook_event_id)');
        $this->addSql('CREATE INDEX agenda_search2_idx ON Agenda (site_id, date_debut)');
        $this->addSql('CREATE INDEX agenda_participations ON Agenda (fb_participations, fb_interets)');
        $this->addSql('CREATE INDEX agenda_search_idx ON Agenda (site_id, date_fin, date_debut)');
        $this->addSql('CREATE INDEX user_nom_idx ON User (nom)');
    }
}
