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

final class Version20261005075134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the has_image virtual column to the events and index the highlights of the portals on it: their query read every upcoming event in full to test its dates and image names';
    }

    public function up(Schema $schema): void
    {
        // VIRTUAL, not STORED: adding a stored column copies the 6 GB table and blocks writes, a virtual one only
        // changes the metadata (INSTANT)
        $this->addSql("ALTER TABLE `event` ADD COLUMN has_image TINYINT(1) GENERATED ALWAYS AS ((image_name IS NOT NULL AND image_name <> '') OR (image_system_name IS NOT NULL AND image_system_name <> '')) VIRTUAL NOT NULL, ALGORITHM=INSTANT");
        // The index materializes has_image; built online (INPLACE, LOCK=NONE): the event table keeps taking writes.
        // It covers the id query of EventRepository::findHighlights() (MySQL has no index condition pushdown on an
        // index over a virtual column, so a query that reads more than its columns would load every upcoming row)
        $this->addSql('ALTER TABLE `event` ADD INDEX event_popular_idx (duplicate_of_id, draft, has_image, end_date, start_date, participations, place_id), ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `event` DROP INDEX event_popular_idx, ALGORITHM=INPLACE, LOCK=NONE');
        $this->addSql('ALTER TABLE `event` DROP COLUMN has_image, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
