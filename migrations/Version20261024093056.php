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

final class Version20261024093056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No coordinates for the events and venues the feeds placed within a degree of 0,0 (SeeTickets and Digitick sent 1,1)';
    }

    public function up(Schema $schema): void
    {
        // As Cleaner::cleanCoordinates() now does on import: 265 events in the dev copy, none of the venues
        $this->addSql('UPDATE event SET latitude = NULL, longitude = NULL WHERE ABS(latitude) <= 1 AND ABS(longitude) <= 1');
        $this->addSql('UPDATE place SET latitude = NULL, longitude = NULL WHERE ABS(latitude) <= 1 AND ABS(longitude) <= 1');
    }

    public function down(Schema $schema): void
    {
        // Putting the venues back in the Gulf of Guinea would only bring the bug back
        $this->throwIrreversibleMigrationException();
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
