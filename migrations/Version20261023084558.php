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

final class Version20261023084558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The slugs of the deleted publishers, redirected to the one that took their events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE publisher_legacy_slug (slug VARCHAR(128) NOT NULL, id INT AUTO_INCREMENT NOT NULL, publisher_id INT NOT NULL, UNIQUE INDEX UNIQ_405238C2989D9B62 (slug), INDEX IDX_405238C240C86FCE (publisher_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE publisher_legacy_slug ADD CONSTRAINT FK_405238C240C86FCE FOREIGN KEY (publisher_id) REFERENCES publisher (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE publisher_legacy_slug');
    }
}
