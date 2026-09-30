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

final class Version20260930151501 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean the tag names: drop the trailing no-break spaces (49 tags, "Humour\u{A0}") and turn the Windows-1252 characters read as C1 controls back into what they were (7 tags, U+0092 → \', U+0096 → –)';
    }

    public function up(Schema $schema): void
    {
        // utf8mb4_unicode_ci already holds "Humour\u{A0}" equal to "Humour": no name can collide with its trimmed self
        $this->addSql("UPDATE tag SET name = REGEXP_REPLACE(name, '\\\\p{Zs}+$', '') WHERE name REGEXP '\\\\p{Zs}$'");
        // A source sent Windows-1252 read as Latin-1: its right quote became U+0092, its en dash U+0096
        $this->addSql("UPDATE tag SET name = REPLACE(name, CONVERT(UNHEX('C292') USING utf8mb4), '''') WHERE name REGEXP '\\\\x{0092}'");
        $this->addSql("UPDATE tag SET name = REPLACE(name, CONVERT(UNHEX('C296') USING utf8mb4), '–') WHERE name REGEXP '\\\\x{0096}'");
    }

    public function down(Schema $schema): void
    {
        // Putting the invisible characters back would only break the tag lookups again
        $this->throwIrreversibleMigrationException();
    }
}
