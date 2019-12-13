<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170511211135 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE INDEX admin_zone_type_idx ON admin_zone (type)');
        $this->addSql('ALTER TABLE zip_city ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE zip_city ADD CONSTRAINT FK_FBE2D3F4727ACA70 FOREIGN KEY (parent_id) REFERENCES admin_zone (id)');
        $this->addSql('CREATE INDEX IDX_FBE2D3F4727ACA70 ON zip_city (parent_id)');
        $this->addSql('CREATE INDEX zip_city_postal_code_name_idx ON zip_city (postal_code, name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX admin_zone_type_idx ON admin_zone');
        $this->addSql('ALTER TABLE zip_city DROP FOREIGN KEY FK_FBE2D3F4727ACA70');
        $this->addSql('DROP INDEX IDX_FBE2D3F4727ACA70 ON zip_city');
        $this->addSql('DROP INDEX zip_city_postal_code_name_idx ON zip_city');
        $this->addSql('ALTER TABLE zip_city DROP parent_id');
    }
}
