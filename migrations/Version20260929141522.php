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

final class Version20260929141522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the lowest price of each event (read from its prices, filled by app:events:backfill-starting-prices), which the agenda filters on';
    }

    public function up(Schema $schema): void
    {
        // Added at the end of the table: MySQL 8 does it instantly, without rebuilding the event table
        $this->addSql('ALTER TABLE event ADD starting_price DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP starting_price');
    }
}
