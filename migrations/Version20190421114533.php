<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190421114533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41DA6A219');
        $this->addSql('ALTER TABLE Agenda CHANGE place_id place_id INT NOT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41DA6A219 FOREIGN KEY (place_id) REFERENCES Place (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Agenda DROP FOREIGN KEY FK_2B41CD41DA6A219');
        $this->addSql('ALTER TABLE Agenda CHANGE place_id place_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Agenda ADD CONSTRAINT FK_2B41CD41DA6A219 FOREIGN KEY (place_id) REFERENCES Place (id)');
    }
}
