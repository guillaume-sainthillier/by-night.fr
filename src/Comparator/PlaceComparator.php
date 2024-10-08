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
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Utils\SluggerUtils;

final class PlaceComparator extends AbstractComparator
{
    public function supports(object $object): bool
    {
        return $object instanceof PlaceDto;
    }

    public function getMatching(object $entity, object $dto): ?MatchingInterface
    {
        \assert($entity instanceof Place);
        \assert($dto instanceof PlaceDto);

        // We cannot compare places of different locations
        if (
            $entity->getCountry()?->getId() !== $dto->country?->entityId
            || $entity->getCity()?->getId() !== $dto->city?->entityId
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

        // ~ Même rue & ~ même nom
        if ($this->getStreetMatchingConfidence($entity->getStreet(), $dto->street) >= 90.0) {
            return new Matching($entity, 100.00);
        }

        // ~ Même nom
        return new Matching($entity, 90.0);
    }

    private function getNameMatchingConfidence(Place $entity, PlaceDto $dto): float
    {
        if (null === $entity->getName() || null === $dto->name) {
            return 0.0;
        }

        if ($this->getStringMatchingConfidence($entity->getName(), $dto->name) >= 100.0) {
            return 100.0;
        }

        $entityPlaceName = $entity->getName();
        if (null !== $entity->getCity()) {
            $entityPlaceName = str_ireplace((string) $entity->getCity()->getName(), '', $entityPlaceName);
        } elseif (null !== $entity->getZipCity()) {
            $entityPlaceName = str_ireplace((string) $entity->getZipCity()->getName(), '', $entityPlaceName);
        }

        $dtoPlaceName = $dto->name;
        if (null !== $dto->city) {
            $dtoPlaceName = str_ireplace((string) $dto->city->name, '', $dtoPlaceName);
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
