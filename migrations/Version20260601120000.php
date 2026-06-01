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

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add place_name_slug table (indexed normalized-name fast path for place de-duplication)';
    }

    public function up(Schema $schema): void
    {
        // country_id must match the legacy charset/collation of country.id (utf8 / utf8_unicode_ci),
        // otherwise MySQL rejects the string foreign key (error 3780). city_id (INT) and place_id are
        // unaffected. The leading DROP makes a previously half-applied run recoverable on re-run.
        $this->addSql('DROP TABLE IF EXISTS place_name_slug');
        $this->addSql('CREATE TABLE place_name_slug (id INT AUTO_INCREMENT NOT NULL, place_id INT NOT NULL, city_id INT DEFAULT NULL, country_id VARCHAR(2) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, slug VARCHAR(255) NOT NULL, INDEX IDX_ABB0C868DA6A219 (place_id), INDEX IDX_ABB0C8688BAC62AF (city_id), INDEX IDX_ABB0C868F92F3E70 (country_id), INDEX place_name_slug_city_idx (city_id, slug), INDEX place_name_slug_country_idx (country_id, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE place_name_slug ADD CONSTRAINT FK_ABB0C868DA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place_name_slug ADD CONSTRAINT FK_ABB0C8688BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE place_name_slug ADD CONSTRAINT FK_ABB0C868F92F3E70 FOREIGN KEY (country_id) REFERENCES country (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE place_name_slug DROP FOREIGN KEY FK_ABB0C868DA6A219');
        $this->addSql('ALTER TABLE place_name_slug DROP FOREIGN KEY FK_ABB0C8688BAC62AF');
        $this->addSql('ALTER TABLE place_name_slug DROP FOREIGN KEY FK_ABB0C868F92F3E70');
        $this->addSql('DROP TABLE place_name_slug');
    }
}
