<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;

final class TagSearchFilter implements FilterInterface
{
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present.
        // It's recommended to add validation (e.g., `required: true`) on the Parameter attribute
        // if the filter logic depends on the value.
        if ($value instanceof ParameterNotFound) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameterName = $queryNameGenerator->generateParameterName('search');

        $queryBuilder
            ->andWhere(\sprintf('%s.name LIKE :%s', $alias, $parameterName))
            ->setParameter($parameterName, '%' . $value . '%');
    }

    // For BC, this function is not useful anymore when documentation occurs on the Parameter
    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
