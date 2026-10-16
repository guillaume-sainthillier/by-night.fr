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

final class Version20261016184630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The search and the status filter of the list an export was started from';
    }

    public function up(Schema $schema): void
    {
        // Two nullable columns on a small table: added in place
        $this->addSql('ALTER TABLE event_export ADD search_query VARCHAR(255) DEFAULT NULL, ADD status_filter VARCHAR(16) DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_export DROP search_query, DROP status_filter');
    }
}
