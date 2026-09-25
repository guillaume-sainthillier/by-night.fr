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

final class Version20260925162954 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the editable portal fields of countries and cities (headline, description, hero image, featured / metropolis flag, display order)';
    }

    public function up(Schema $schema): void
    {
        // Adding columns is an INSTANT change in MySQL 8: no copy of the ~88k admin_zone rows.
        // The city columns are nullable because admin_zone also holds the ADM1/ADM2 rows (single-table inheritance).
        $this->addSql('ALTER TABLE admin_zone ADD headline VARCHAR(255) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD is_metropolis TINYINT DEFAULT 0, ADD display_order SMALLINT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD hero_image_name VARCHAR(255) DEFAULT NULL, ADD hero_image_original_name VARCHAR(255) DEFAULT NULL, ADD hero_image_mime_type VARCHAR(255) DEFAULT NULL, ADD hero_image_size INT DEFAULT NULL, ADD hero_image_dimensions LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE country ADD headline VARCHAR(255) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD hero_caption VARCHAR(255) DEFAULT NULL, ADD is_featured TINYINT DEFAULT 0 NOT NULL, ADD display_order SMALLINT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD hero_image_name VARCHAR(255) DEFAULT NULL, ADD hero_image_original_name VARCHAR(255) DEFAULT NULL, ADD hero_image_mime_type VARCHAR(255) DEFAULT NULL, ADD hero_image_size INT DEFAULT NULL, ADD hero_image_dimensions LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone DROP headline, DROP description, DROP is_metropolis, DROP display_order, DROP updated_at, DROP hero_image_name, DROP hero_image_original_name, DROP hero_image_mime_type, DROP hero_image_size, DROP hero_image_dimensions');
        $this->addSql('ALTER TABLE country DROP headline, DROP description, DROP hero_caption, DROP is_featured, DROP display_order, DROP updated_at, DROP hero_image_name, DROP hero_image_original_name, DROP hero_image_mime_type, DROP hero_image_size, DROP hero_image_dimensions');
    }
}
