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
 * The towns next door of a city page (EventRepository::findUpcomingCitiesNearest()) were read from the type index:
 * every city (~88k) to keep the ~100 in a 30 km box, ~180 ms, twice a second, MySQL's busiest query. The latitude
 * now bounds the range, and the longitude and the count of events to come are tested in the index before any row
 * is read. Built in place, without locking the table (~12 MB).
 */
final class Version20261029071429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the cities by latitude for the towns next door of a city page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX admin_zone_type_latitude_idx ON admin_zone (type, latitude, longitude, upcoming_events) ALGORITHM=INPLACE LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX admin_zone_type_latitude_idx ON admin_zone');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
