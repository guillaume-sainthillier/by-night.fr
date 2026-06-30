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

final class Version20260630120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parser_data.content_hash so the import command can skip re-enqueueing unchanged events';
    }

    public function up(Schema $schema): void
    {
        // Nullable on purpose: existing rows backfill their hash on the next run that
        // actually observes the event, so no data migration is required.
        $this->addSql('ALTER TABLE parser_data ADD content_hash VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parser_data DROP content_hash');
    }
}
