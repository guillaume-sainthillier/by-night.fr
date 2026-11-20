<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\Query;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\SelectClause;
use Doctrine\ORM\Query\SqlOutputWalker;

/**
 * Tells MySQL which index to read a table of a query from, when its planner picks a worse one: the INDEX optimizer
 * hint (MySQL 8.0.20+), written as a comment right after SELECT, which DQL cannot write. Any other database (SQLite,
 * the tests) reads a comment, so the query runs there as is.
 *
 * Through IndexHintWalker::useIndex(), never by setting its hints by hand.
 */
final class IndexHintWalker extends SqlOutputWalker
{
    private const string HINT = 'app.index_hints';

    /**
     * Reads the entity of $dqlAlias from $index.
     */
    public static function useIndex(Query $query, string $dqlAlias, string $index): Query
    {
        /** @var array<string, string> $indexes */
        $indexes = $query->getHint(self::HINT) ?: [];
        $indexes[$dqlAlias] = $index;

        return $query
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, self::class)
            ->setHint(self::HINT, $indexes);
    }

    public function walkSelectClause(SelectClause $selectClause): string
    {
        $sql = parent::walkSelectClause($selectClause);

        /** @var array<string, string> $indexes */
        $indexes = $this->getQuery()->getHint(self::HINT) ?: [];
        $hints = [];
        foreach ($indexes as $dqlAlias => $index) {
            $tableAlias = $this->getSQLTableAlias($this->getMetadataForDqlAlias($dqlAlias)->getTableName(), $dqlAlias);
            $hints[] = \sprintf('INDEX(%s %s)', $tableAlias, $index);
        }

        if ([] === $hints) {
            return $sql;
        }

        return 'SELECT /*+ ' . implode(' ', $hints) . ' */ ' . substr($sql, \strlen('SELECT '));
    }
}
