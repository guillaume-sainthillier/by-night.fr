<?php

namespace App\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170512194237 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX admin_zone_type_idx ON admin_zone');
        $this->addSql('CREATE INDEX admin_zone_type_name_idx ON admin_zone (type, name)');
        $this->addSql('DROP INDEX zip_city_postal_code_name_idx ON zip_city');
        $this->addSql('CREATE INDEX zip_city_postal_code_name_idx ON zip_city (name, postal_code)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX admin_zone_type_name_idx ON admin_zone');
        $this->addSql('CREATE INDEX admin_zone_type_idx ON admin_zone (type)');
        $this->addSql('DROP INDEX zip_city_postal_code_name_idx ON zip_city');
        $this->addSql('CREATE INDEX zip_city_postal_code_name_idx ON zip_city (postal_code, name)');
    }
}
