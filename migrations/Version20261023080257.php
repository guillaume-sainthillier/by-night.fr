<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023080257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The page of a publisher: its description, website, social links, logo and cover';
    }

    public function up(Schema $schema): void
    {
        // The links are added nullable, given an empty list, then required: a JSON column takes no literal default
        $this->addSql('ALTER TABLE publisher ADD description LONGTEXT DEFAULT NULL, ADD website VARCHAR(255) DEFAULT NULL, ADD social_links JSON DEFAULT NULL, ADD logo_name VARCHAR(255) DEFAULT NULL, ADD logo_original_name VARCHAR(255) DEFAULT NULL, ADD logo_mime_type VARCHAR(255) DEFAULT NULL, ADD logo_size INT DEFAULT NULL, ADD logo_dimensions LONGTEXT DEFAULT NULL, ADD cover_name VARCHAR(255) DEFAULT NULL, ADD cover_original_name VARCHAR(255) DEFAULT NULL, ADD cover_mime_type VARCHAR(255) DEFAULT NULL, ADD cover_size INT DEFAULT NULL, ADD cover_dimensions LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE publisher SET social_links = '[]'");
        $this->addSql('ALTER TABLE publisher MODIFY social_links JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publisher DROP description, DROP website, DROP social_links, DROP logo_name, DROP logo_original_name, DROP logo_mime_type, DROP logo_size, DROP logo_dimensions, DROP cover_name, DROP cover_original_name, DROP cover_mime_type, DROP cover_size, DROP cover_dimensions');
    }
}
