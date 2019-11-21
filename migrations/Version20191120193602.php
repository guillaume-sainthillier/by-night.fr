<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191120193602 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE Place p SET p.created_at = (SELECT MIN(a.created_at) FROM Agenda a WHERE a.place_id = p.id)');
        $this->addSql('UPDATE Place p SET p.updated_at = (SELECT MAX(a.updated_at) FROM Agenda a WHERE a.place_id = p.id)');
        $this->addSql('UPDATE Place p SET p.created_at = NOW() WHERE p.created_at IS NULL');
        $this->addSql('UPDATE Place p SET p.updated_at = NOW() WHERE p.updated_at IS NULL');
        $this->addSql('UPDATE Calendrier c SET c.created_at = NOW() WHERE c.created_at IS NULL');
        $this->addSql('UPDATE Calendrier c SET c.updated_at = c.created_at');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
