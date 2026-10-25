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
use Override;

/**
 * The events and members deleted before DeletedPageListener: MySQL never gives an id again, so every id from 1 to the
 * table's highest that no row has was deleted (or burnt by a rolled-back insert, which no page ever showed). About
 * 916k events and 226 members in the dev copy of 2026-09-22; their deletion date is unknown (null).
 *
 * Not transactional, read committed: under repeatable read, INSERT … SELECT would hold a shared lock on every event
 * row it reads, holding up the import workers for the minute it takes. INSERT IGNORE skips what the listener already
 * recorded.
 */
final class Version20261025120725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the events and members deleted so far, from the gaps of their ids';
    }

    #[Override]
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->addSql('SET SESSION cte_max_recursion_depth = 4294967295');

        foreach (['event', 'user'] as $table) {
            // An empty table has no id to start from
            $this->addSql(\sprintf(<<<'SQL'
                INSERT IGNORE INTO deleted_page (kind, identifier, deleted_at)
                WITH RECURSIVE ids (id) AS (
                    SELECT 1 FROM DUAL WHERE EXISTS (SELECT 1 FROM %1$s)
                    UNION ALL
                    SELECT id + 1 FROM ids WHERE id < (SELECT MAX(id) FROM %1$s)
                )
                SELECT '%1$s', CAST(ids.id AS CHAR), NULL FROM ids WHERE NOT EXISTS (SELECT 1 FROM %1$s t WHERE t.id = ids.id)
                SQL, $table));
        }

        $this->addSql('SET SESSION cte_max_recursion_depth = DEFAULT');
        $this->addSql('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM deleted_page WHERE kind IN ('event', 'user') AND deleted_at IS NULL");
    }
}
