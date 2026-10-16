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

final class Version20261016185119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The warned events a member chose not to import, and how many the import skipped';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import ADD skipped_count INT DEFAULT 0 NOT NULL, ADD skipped_rows JSON DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import DROP skipped_count, DROP skipped_rows');
    }
}
