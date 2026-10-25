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
 * The pages of the events, members, publishers and venues deleted from now on answer 410 Gone (DeletedPageListener).
 * The events and members deleted before are recorded by the next migration; the publishers and venues are not known:
 * their slugs are gone with them.
 */
final class Version20261025113945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the pages of deleted events, members, publishers and venues';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE deleted_page (deleted_at DATETIME DEFAULT NULL, kind VARCHAR(16) NOT NULL, identifier VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, UNIQUE INDEX deleted_page_unique (kind, identifier), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE deleted_page');
    }
}
