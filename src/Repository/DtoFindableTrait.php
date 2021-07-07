<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\ExternalIdentifiableInterface;
use Doctrine\ORM\QueryBuilder;

trait DtoFindableTrait
{
    protected function addDtosToQueryBuilding(QueryBuilder $queryBuilder, $rootAlias, array $dtos): void
    {
        $groupedWheres = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof ExternalIdentifiableInterface);

            if (null === $dto->getExternalId() || null === $dto->getExternalOrigin()) {
                continue;
            }

            $groupedWheres[$dto->getExternalOrigin()][$dto->getExternalId()] = true;
        }

        if (0 === \count($groupedWheres)) {
            return;
        }

        $i = 1;
        $wheres = [];
        foreach ($groupedWheres as $externalOrigin => $ids) {
            $externalOriginPlaceholder = sprintf('externalOrigin_%d', $i);
            $externalIdsPlaceholder = sprintf('externalIds_%d', $i);
            $wheres[] = sprintf(
                '(%s.externalOrigin = :%s AND %s.externalId IN(:%s))',
                $rootAlias,
                $externalOriginPlaceholder,
                $rootAlias,
                $externalIdsPlaceholder
            );

            $queryBuilder
                ->setParameter($externalOriginPlaceholder, $externalOrigin)
                ->setParameter($externalIdsPlaceholder, array_keys($ids));
            ++$i;
        }

        $queryBuilder->orWhere(implode(' OR ', $wheres));
    }
}
