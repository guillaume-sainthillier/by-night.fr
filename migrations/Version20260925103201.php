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

final class Version20260925103201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record when the images of an event were removed on request, so they are never downloaded again';
    }

    public function up(Schema $schema): void
    {
        // A nullable column appended to the table: MySQL 8 adds it instantly, without copying the rows
        $this->addSql('ALTER TABLE event ADD image_removed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP image_removed_at');
    }
}
