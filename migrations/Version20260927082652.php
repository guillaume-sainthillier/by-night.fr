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

final class Version20260927082652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the slugs of the places merged by app:places:merge-duplicates, so their agenda pages redirect to the place that took their events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE place_legacy_slug (slug VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, place_id INT NOT NULL, INDEX place_legacy_slug_slug_idx (slug), INDEX IDX_B9D868DEDA6A219 (place_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE place_legacy_slug ADD CONSTRAINT FK_B9D868DEDA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE place_legacy_slug');
    }
}
