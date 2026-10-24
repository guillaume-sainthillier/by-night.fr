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
 * The check keeps the member's row of each row of its file of kept rows, which leaves the refused and blank rows out:
 * the import's report names the rows of the member's file, as the check's errors and warnings do. The imports checked
 * before keep the rows of the check's file.
 */
final class Version20261024115025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep the member\'s rows of the rows an import\'s check kept';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import ADD kept_rows JSON DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_import DROP kept_rows');
    }
}
