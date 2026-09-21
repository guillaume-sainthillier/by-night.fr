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

final class Version20260921110526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index event.from_data (import source), listed by the back-office "Source" filter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX event_from_data_idx ON `event` (from_data)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX event_from_data_idx ON `event`');
    }
}
