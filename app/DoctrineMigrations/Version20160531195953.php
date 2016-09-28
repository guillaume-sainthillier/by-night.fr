<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160531195953 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE image DROP FOREIGN KEY FK_4FC2B5BF6BD1646');
        $this->addSql('DROP INDEX IDX_4FC2B5BF6BD1646 ON image');
        $this->addSql('ALTER TABLE image DROP site_id');
        $this->addSql('ALTER TABLE user ADD image_id INT DEFAULT NULL, CHANGE show_socials show_socials TINYINT(1) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_2DA179773DA5256D FOREIGN KEY (image_id) REFERENCES Image (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2DA179773DA5256D ON user (image_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Image ADD site_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE Image ADD CONSTRAINT FK_4FC2B5BF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('CREATE INDEX IDX_4FC2B5BF6BD1646 ON Image (site_id)');
        $this->addSql('ALTER TABLE User DROP FOREIGN KEY FK_2DA179773DA5256D');
        $this->addSql('DROP INDEX UNIQ_2DA179773DA5256D ON User');
        $this->addSql('ALTER TABLE User DROP image_id, CHANGE show_socials show_socials TINYINT(1) DEFAULT \'1\'');
    }
}
