<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200519180857 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE event SET reservation_internet = NULL WHERE LENGTH(reservation_internet) = 0');
        $this->addSql('UPDATE event SET reservation_telephone = NULL WHERE LENGTH(reservation_telephone) = 0');
        $this->addSql('UPDATE event SET reservation_email = NULL WHERE LENGTH(reservation_email) = 0');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
