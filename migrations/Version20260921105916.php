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

final class Version20260921105916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move the downloaded image hash wrongly stored by EventHandler in event.image_hash to event.image_system_hash';
    }

    public function up(Schema $schema): void
    {
        // Backfill first: the importer skips re-uploading an unchanged image by comparing image_system_hash.
        // ImageSubscriber normally set it too, so this only catches rows where that failed.
        $this->addSql('UPDATE event SET image_system_hash = image_hash WHERE image_system_hash IS NULL AND image_hash IS NOT NULL AND image_system_name IS NOT NULL');

        // Then drop the copies: without a user image, an image_hash equal to the system one can only come from the bug
        $this->addSql('UPDATE event SET image_hash = NULL WHERE image_name IS NULL AND image_hash IS NOT NULL AND image_hash = image_system_hash');
    }

    public function down(Schema $schema): void
    {
        // Data fix, no faithful reverse: the rows moved above cannot be told apart afterwards, and
        // restoring the copies would only bring the bug back (the previous code never reads image_hash).
    }
}
