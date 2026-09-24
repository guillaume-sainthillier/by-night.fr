<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\ExternalIdentifiableInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

trait DtoFindableTrait
{
    protected function addDtosToQueryBuilder(QueryBuilder $queryBuilder, string $rootAlias, array $dtos): void
    {
        $groupedWheres = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof ExternalIdentifiableInterface);

            if (null === $dto->getExternalId() || null === $dto->getExternalOrigin()) {
                continue;
            }

            $groupedWheres[$dto->getExternalOrigin()][$dto->getExternalId()] = true;
        }

        if ([] === $groupedWheres) {
            return;
        }

        $i = 1;
        $wheres = [];
        foreach ($groupedWheres as $externalOrigin => $ids) {
            $externalOriginPlaceholder = \sprintf('externalOrigin_%d', $i);
            $externalIdsPlaceholder = \sprintf('externalIds_%d', $i);
            $wheres[] = \sprintf(
                '(%s.externalOrigin = :%s AND %s.externalId IN(:%s))',
                $rootAlias,
                $externalOriginPlaceholder,
                $rootAlias,
                $externalIdsPlaceholder
            );

            // Array keys turn numeric ids ("111293") into ints, and Doctrine infers an array's
            // type from its first element: bound as integers, MySQL would compare the VARCHAR
            // column numerically, so every "FMA…" id (cast to 0) would match — hundreds of
            // thousands of rows for one batch.
            $queryBuilder
                ->setParameter($externalOriginPlaceholder, $externalOrigin)
                ->setParameter($externalIdsPlaceholder, array_map(strval(...), array_keys($ids)), ArrayParameterType::STRING);
            ++$i;
        }

        $queryBuilder->orWhere(implode(' OR ', $wheres));
    }
}
