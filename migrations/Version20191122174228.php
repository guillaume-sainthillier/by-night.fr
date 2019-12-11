<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191122174228 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE country ADD display_name VARCHAR(63) NOT NULL, ADD at_display_name VARCHAR(63) NOT NULL');
        $this->addSql('UPDATE country SET display_name = name, at_display_name = CONCAT(\'en \', name)');
        $this->addSql('UPDATE country SET display_name = \'La Réunion\', at_display_name = \'à La Réunion\' WHERE id = \'RE\'');
        $this->addSql('UPDATE country SET at_display_name = CONCAT(\'à \', name) WHERE id IN(\'MC\', \'YT\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE country DROP display_name, DROP at_display_name');
    }
}
