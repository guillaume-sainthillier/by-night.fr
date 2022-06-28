<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;
use App\Dto\CountryDto;
use App\Entity\Country;
use App\Utils\SluggerUtils;

class CountryComparator extends AbstractComparator
{
    public function __construct(private SluggerUtils $sluggerUtils)
    {
    }

    public function supports(object $object): bool
    {
        return $object instanceof CountryDto;
    }

    public function getMatching(object $entity, object $dto): ?MatchingInterface
    {
        \assert($entity instanceof Country);
        \assert($dto instanceof CountryDto);

        if ($entity->getId() === $dto->code || $entity->getId() === $dto->name) {
            return new Matching($entity, 100.0);
        }

        if (null === $dto->name || (null === $entity->getName() && null === $entity->getDisplayName())) {
            return null;
        }

        $dtoName = $this->sluggerUtils->generateSlug($dto->name);

        if (null !== $entity->getName() && $this->sluggerUtils->generateSlug($entity->getName()) === $dtoName) {
            return new Matching($entity, 100.0);
        }

        if (null !== $entity->getDisplayName() && $this->sluggerUtils->generateSlug($entity->getDisplayName()) === $dtoName) {
            return new Matching($entity, 100.0);
        }

        return null;
    }
}
