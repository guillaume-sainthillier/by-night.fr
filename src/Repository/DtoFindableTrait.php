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
        $alreadyAdded = [];
        $wheres = [];
        $i = 0;
        foreach ($dtos as $dto) {
            \assert($dto instanceof ExternalIdentifiableInterface);

            if (null === $dto->getExternalId() && null === $dto->getExternalOrigin()) {
                continue;
            }

            $key = sprintf('%s.%s', $dto->getExternalId(), $dto->getExternalOrigin());
            if (isset($alreadyAdded[$key])) {
                continue;
            }

            $alreadyAdded[$key] = true;
            $externalIdPlaceholder = sprintf('externalId_%d', $i);
            $externalOriginPlaceholder = sprintf('externalOrigin_%d', $i);
            $wheres[] = sprintf(
                '(%s.externalId = :%s AND %s.externalOrigin = :%s)',
                $rootAlias,
                $externalIdPlaceholder,
                $rootAlias,
                $externalOriginPlaceholder
            );

            $queryBuilder
                ->setParameter($externalIdPlaceholder, $dto->getExternalId())
                ->setParameter($externalOriginPlaceholder, $dto->getExternalOrigin());
            ++$i;
        }

        if (0 === \count($wheres)) {
            return;
        }

        $queryBuilder->orWhere(implode(' OR ', $wheres));
    }
}
