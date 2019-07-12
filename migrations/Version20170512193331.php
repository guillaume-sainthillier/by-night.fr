<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170512193331 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Place ADD city_id INT DEFAULT NULL, ADD zip_city_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Place ADD CONSTRAINT FK_B5DC7CC98BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id)');
        $this->addSql('ALTER TABLE Place ADD CONSTRAINT FK_B5DC7CC9309D0B4F FOREIGN KEY (zip_city_id) REFERENCES zip_city (id)');
        $this->addSql('CREATE INDEX IDX_B5DC7CC98BAC62AF ON Place (city_id)');
        $this->addSql('CREATE INDEX IDX_B5DC7CC9309D0B4F ON Place (zip_city_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Place DROP FOREIGN KEY FK_B5DC7CC98BAC62AF');
        $this->addSql('ALTER TABLE Place DROP FOREIGN KEY FK_B5DC7CC9309D0B4F');
        $this->addSql('DROP INDEX IDX_B5DC7CC98BAC62AF ON Place');
        $this->addSql('DROP INDEX IDX_B5DC7CC9309D0B4F ON Place');
        $this->addSql('ALTER TABLE Place DROP city_id, DROP zip_city_id');
    }
}
