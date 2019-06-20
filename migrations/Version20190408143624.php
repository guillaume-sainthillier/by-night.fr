<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190408143624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE Agenda a 
            LEFT JOIN Place p ON p.id = a.place_id 
            SET a.place_name = p.nom, 
                a.place_street = p.rue, 
                a.place_city = p.ville, 
                a.place_postal_code = p.code_postal, 
                a.place_external_id = p.external_id, 
                a.place_facebook_id = p.facebook_id
            ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
