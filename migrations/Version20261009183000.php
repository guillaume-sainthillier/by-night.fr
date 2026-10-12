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

final class Version20261009183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the location of a merged place\'s slug, so a place merged into one of another city still redirects from its own city';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_legacy_slug ADD city_id INT DEFAULT NULL, ADD country_id VARCHAR(2) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
        // The places merged so far were merged within their city: the slug was in the location of the place it leads to
        $this->addSql('UPDATE place_legacy_slug l INNER JOIN place p ON p.id = l.place_id SET l.city_id = p.city_id, l.country_id = p.country_id');
        $this->addSql('ALTER TABLE place_legacy_slug ADD CONSTRAINT FK_B9D868DE8BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place_legacy_slug ADD CONSTRAINT FK_B9D868DEF92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_B9D868DE8BAC62AF ON place_legacy_slug (city_id)');
        $this->addSql('CREATE INDEX IDX_B9D868DEF92F3E70 ON place_legacy_slug (country_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_legacy_slug DROP FOREIGN KEY FK_B9D868DE8BAC62AF');
        $this->addSql('ALTER TABLE place_legacy_slug DROP FOREIGN KEY FK_B9D868DEF92F3E70');
        $this->addSql('DROP INDEX IDX_B9D868DE8BAC62AF ON place_legacy_slug');
        $this->addSql('DROP INDEX IDX_B9D868DEF92F3E70 ON place_legacy_slug');
        $this->addSql('ALTER TABLE place_legacy_slug DROP city_id, DROP country_id');
    }
}
