<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;
use App\Dto\CityDto;
use App\Entity\City;
use App\Utils\SluggerUtils;

final class CityComparator extends AbstractComparator
{
    public function __construct(private readonly SluggerUtils $sluggerUtils)
    {
    }

    public function supports(object $object): bool
    {
        return $object instanceof CityDto;
    }

    public function getMatching(object $entity, object $dto): ?MatchingInterface
    {
        \assert($entity instanceof City);
        \assert($dto instanceof CityDto);

        // We don't compare cities of different countries
        if ($dto->country->entityId !== $entity->getCountry()->getId()) {
            return null;
        }

        if (null === $dto->name || null === $entity->getName()) {
            return null;
        }

        $entityName = $this->sanitize($entity->getName());
        $dtoName = $this->sanitize($dto->name);

        if ($this->sluggerUtils->generateSlug($entityName) === $this->sluggerUtils->generateSlug($dtoName)) {
            return new Matching($entity, 100.0);
        }

        return null;
    }
}
