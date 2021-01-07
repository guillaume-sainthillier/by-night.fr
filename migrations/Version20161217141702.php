<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
class Version20161217141702 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Site DROP adjectif_singulier, DROP adjectif_pluriel, DROP description, DROP facebook_id_page, DROP google_id_page, DROP twitter_id_page');
        $this->addSql('UPDATE Agenda SET url = REPLACE(url, "http://parisinfo.com/", "http://www.parisinfo.com/")');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' != $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE Site ADD adjectif_singulier VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD adjectif_pluriel VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD description LONGTEXT NOT NULL COLLATE utf8_unicode_ci, ADD facebook_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci, ADD google_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci, ADD twitter_id_page VARCHAR(127) DEFAULT NULL COLLATE utf8_unicode_ci');
    }
}
