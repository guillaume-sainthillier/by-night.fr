<?php

namespace App\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20161217141702 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Site DROP adjectif_singulier, DROP adjectif_pluriel, DROP description, DROP facebook_id_page, DROP google_id_page, DROP twitter_id_page');
        $this->addSql('UPDATE Agenda SET url = REPLACE(url, "http://parisinfo.com/", "http://www.parisinfo.com/")');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Site ADD adjectif_singulier VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD adjectif_pluriel VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD description LONGTEXT NOT NULL COLLATE utf8_unicode_ci, ADD facebook_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci, ADD google_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci, ADD twitter_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
