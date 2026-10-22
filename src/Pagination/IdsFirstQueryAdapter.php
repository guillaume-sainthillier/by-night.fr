<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Pagination;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

/**
 * Reads a page in two queries: the ids of the page, sorted and limited, then the rows of those ids (a "deferred
 * join"). Sorting the query itself makes MySQL sort every selected column of every matching row: on event, with its
 * TEXT columns, the sort buffer overflows to disk (sort merge passes) to keep a handful of rows.
 *
 * Only for a query joining no to-many association (each root once) whose ORDER BY names columns, not select aliases;
 * only the root entities are loaded, their associations are left to MultipleEagerLoaderInterface. Counted as
 * PaginateTrait counts without fetchJoinCollection: COUNT(DISTINCT id).
 *
 * @template T of object
 *
 * @implements AdapterInterface<T>
 */
final readonly class IdsFirstQueryAdapter implements AdapterInterface
{
    /** @var QueryAdapter<T> */
    private QueryAdapter $countAdapter;

    public function __construct(private QueryBuilder $queryBuilder)
    {
        $this->countAdapter = new QueryAdapter($queryBuilder, fetchJoinCollection: false, useOutputWalkers: false);
    }

    public function getNbResults(): int
    {
        return $this->countAdapter->getNbResults();
    }

    /**
     * @return list<T>
     */
    public function getSlice(int $offset, int $length): iterable
    {
        $alias = $this->queryBuilder->getRootAliases()[0];
        /** @var class-string<T> $class */
        $class = $this->queryBuilder->getRootEntities()[0];
        $entityManager = $this->queryBuilder->getEntityManager();
        $idField = $entityManager->getClassMetadata($class)->getSingleIdentifierFieldName();

        /** @var list<int|string> $ids */
        $ids = (clone $this->queryBuilder)
            ->select(\sprintf('%s.%s', $alias, $idField))
            ->setFirstResult($offset)
            ->setMaxResults($length)
            ->getQuery()
            ->getSingleColumnResult();

        if ([] === $ids) {
            return [];
        }

        /** @var array<int|string, T> $entities */
        $entities = $entityManager
            ->createQueryBuilder()
            ->select('x')
            ->from($class, 'x', 'x.' . $idField)
            ->where(\sprintf('x.%s IN (:ids)', $idField))
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // In the order of the ids; a row deleted in between is left out
        return array_values(array_filter(array_map(static fn (int|string $id): ?object => $entities[$id] ?? null, $ids)));
    }
}
