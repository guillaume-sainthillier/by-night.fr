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

final class Version20261005075108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the events of a venue by start date: the similar events of an event page (same day, same city) read every event ever held at each venue of the city through event_place_upcoming_idx on busy days';
    }

    public function up(Schema $schema): void
    {
        // Built online (INPLACE, LOCK=NONE): the event table keeps taking writes during the build
        $this->addSql('ALTER TABLE `event` ADD INDEX event_place_start_date_idx (place_id, start_date), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP INDEX event_place_start_date_idx, ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
