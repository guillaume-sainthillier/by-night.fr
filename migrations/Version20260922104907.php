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

final class Version20260922104907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parser state: when each parser last ran, the lower bound of its next incremental import (app:events:import)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE parser_state (id INT AUTO_INCREMENT NOT NULL, parser VARCHAR(63) NOT NULL, last_parsed_at DATETIME NOT NULL, UNIQUE INDEX parser_state_parser_unique (parser), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE parser_state');
    }
}
