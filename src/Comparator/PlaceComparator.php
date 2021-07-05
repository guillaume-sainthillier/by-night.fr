<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Utils\SluggerUtils;

class PlaceComparator extends AbstractComparator
{
    public function supports(object $object): bool
    {
        return $object instanceof PlaceDto;
    }

    public function getMatching(object $entity, object $dto): ?MatchingInterface
    {
        \assert($entity instanceof Place);
        \assert($dto instanceof PlaceDto);

        //We cannot compare places of different locations
        if (
            (
                null === $entity->getCountry() ||
                null === $dto->country ||
                $entity->getCountry()->getId() !== $dto->country->id
            ) &&
            (
                null === $entity->getCity() ||
                null === $dto->city ||
                $entity->getCity()->getId() !== $dto->city->id
            ) &&
            (
                null === $entity->getZipCity() ||
                null === $dto->zipCity ||
                $entity->getZipCity()->getId() !== $dto->zipCity->id
            )
        ) {
            return null;
        }

        $nameMatchingConfidence = $this->getNameMatchingConfidence(
            $entity,
            $dto,
        );

        if ($nameMatchingConfidence < 80.0) {
            return null;
        }

        //Même rue & ~ même nom
        if ($this->getStreetMatchingConfidence($entity->getRue(), $dto->street) >= 90.0) {
            return new Matching($entity, 100.00);
        }

        //~ Même nom
        return new Matching($entity, 90.0);
    }

    private function getNameMatchingConfidence(Place $entity, PlaceDto $dto): float
    {
        if (null === $entity->getNom() || null === $dto->name) {
            return 0.0;
        }

        if ($this->getStringMatchingConfidence($entity->getNom(), $dto->name) >= 100.0) {
            return 100.0;
        }

        $entityPlaceName = $entity->getNom();
        if (null !== $entity->getCity()) {
            $entityPlaceName = str_ireplace($entity->getCity()->getName(), '', $entityPlaceName);
        } elseif (null !== $entity->getZipCity()) {
            $entityPlaceName = str_ireplace($entity->getZipCity()->getName(), '', $entityPlaceName);
        }

        $dtoPlaceName = $dto->name;
        if (null !== $dto->city) {
            $dtoPlaceName = str_ireplace($dto->city->name, '', $dtoPlaceName);
        } elseif (null !== $dto->zipCity) {
            $dtoPlaceName = str_ireplace($dto->zipCity->name, '', $dtoPlaceName);
        }

        $entityPlaceName = $this->sanitize($entityPlaceName);
        $dtoPlaceName = $this->sanitize($dtoPlaceName);

        return $this->getStringMatchingConfidence($entityPlaceName, $dtoPlaceName);
    }

    private function getStreetMatchingConfidence(?string $leftText, ?string $rightText): float
    {
        if (null !== $leftText && $leftText === $rightText) {
            return 100.0;
        }

        $trimedA = $this->sanitizeStreetName($leftText);
        $trimedB = $this->sanitizeStreetName($rightText);

        return $this->getStringMatchingConfidence($trimedA, $trimedB);
    }

    public function sanitizeStreetName(?string $string): ?string
    {
        if (null === $string) {
            return null;
        }

        return SluggerUtils::generateSlug($string);
    }
}
