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

/**
 * The official code of a city's commune and the commune names GeoNames lacks, both filled by
 * `app:cities:import-official-data` after the deploy (until then, nothing reads them).
 */
final class Version20261028213821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the official commune code of the cities and their aliases';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_zone ADD official_code VARCHAR(10) DEFAULT NULL, ALGORITHM=INSTANT');
        $this->addSql('CREATE INDEX admin_zone_country_official_code_idx ON admin_zone (country_id, official_code) ALGORITHM=INPLACE LOCK=NONE');
        $this->addSql('CREATE TABLE city_alias (name_key VARCHAR(200) NOT NULL, name VARCHAR(200) NOT NULL, id INT AUTO_INCREMENT NOT NULL, city_id INT NOT NULL, INDEX city_alias_name_key_idx (name_key), UNIQUE INDEX city_alias_city_name_key_unique (city_id, name_key), INDEX IDX_A2E6CCC38BAC62AF (city_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE city_alias ADD CONSTRAINT FK_A2E6CCC38BAC62AF FOREIGN KEY (city_id) REFERENCES admin_zone (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE city_alias');
        $this->addSql('DROP INDEX admin_zone_country_official_code_idx ON admin_zone');
        $this->addSql('ALTER TABLE admin_zone DROP official_code');
    }
}
