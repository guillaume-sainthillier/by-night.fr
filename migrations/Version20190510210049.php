<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190510210049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_theme_manifestation_idx TO event_theme_manifestation_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_type_manifestation_idx TO event_type_manifestation_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_categorie_manifestation_idx TO event_categorie_manifestation_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_search_idx TO event_search_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_fb_participations TO event_fb_participations');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX agenda_external_id_idx TO event_external_id_idx');
        $this->addSql('ALTER TABLE Comment DROP FOREIGN KEY FK_5BC96BF0EA67784A');
        $this->addSql('DROP INDEX IDX_5BC96BF0EA67784A ON Comment');
        $this->addSql('ALTER TABLE Comment CHANGE agenda_id event_id INT NOT NULL');
        $this->addSql('ALTER TABLE Comment ADD CONSTRAINT FK_5BC96BF071F7E88B FOREIGN KEY (event_id) REFERENCES Agenda (id)');
        $this->addSql('CREATE INDEX IDX_5BC96BF071F7E88B ON Comment (event_id)');
        $this->addSql('ALTER TABLE Calendrier DROP FOREIGN KEY FK_FD283F69EA67784A');
        $this->addSql('DROP INDEX IDX_FD283F69EA67784A ON Calendrier');
        $this->addSql('DROP INDEX user_agenda_unique ON Calendrier');
        $this->addSql('ALTER TABLE Calendrier CHANGE agenda_id event_id INT NOT NULL');
        $this->addSql('ALTER TABLE Calendrier ADD CONSTRAINT FK_FD283F6971F7E88B FOREIGN KEY (event_id) REFERENCES Agenda (id)');
        $this->addSql('CREATE INDEX IDX_FD283F6971F7E88B ON Calendrier (event_id)');
        $this->addSql('CREATE UNIQUE INDEX user_event_unique ON Calendrier (user_id, event_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_external_id_idx TO agenda_external_id_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_type_manifestation_idx TO agenda_type_manifestation_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_fb_participations TO agenda_fb_participations');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_search_idx TO agenda_search_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_theme_manifestation_idx TO agenda_theme_manifestation_idx');
        $this->addSql('ALTER TABLE Agenda RENAME INDEX event_categorie_manifestation_idx TO agenda_categorie_manifestation_idx');
        $this->addSql('ALTER TABLE Calendrier DROP FOREIGN KEY FK_FD283F6971F7E88B');
        $this->addSql('DROP INDEX IDX_FD283F6971F7E88B ON Calendrier');
        $this->addSql('DROP INDEX user_event_unique ON Calendrier');
        $this->addSql('ALTER TABLE Calendrier CHANGE event_id agenda_id INT NOT NULL');
        $this->addSql('ALTER TABLE Calendrier ADD CONSTRAINT FK_FD283F69EA67784A FOREIGN KEY (agenda_id) REFERENCES Agenda (id)');
        $this->addSql('CREATE INDEX IDX_FD283F69EA67784A ON Calendrier (agenda_id)');
        $this->addSql('CREATE UNIQUE INDEX user_agenda_unique ON Calendrier (user_id, agenda_id)');
        $this->addSql('ALTER TABLE Comment DROP FOREIGN KEY FK_5BC96BF071F7E88B');
        $this->addSql('DROP INDEX IDX_5BC96BF071F7E88B ON Comment');
        $this->addSql('ALTER TABLE Comment CHANGE event_id agenda_id INT NOT NULL');
        $this->addSql('ALTER TABLE Comment ADD CONSTRAINT FK_5BC96BF0EA67784A FOREIGN KEY (agenda_id) REFERENCES Agenda (id)');
        $this->addSql('CREATE INDEX IDX_5BC96BF0EA67784A ON Comment (agenda_id)');
    }
}
