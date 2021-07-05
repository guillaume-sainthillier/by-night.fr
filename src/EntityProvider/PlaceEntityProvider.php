<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\EntityProviderInterface;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Handler\ComparatorHandler;
use App\Repository\PlaceRepository;

class PlaceEntityProvider implements EntityProviderInterface
{
    private PlaceRepository $placeRepository;

    private ComparatorHandler $comparatorHandler;

    /** @var Place[] */
    private array $places = [];

    public function __construct(PlaceRepository $placeRepository, ComparatorHandler $comparatorHandler)
    {
        $this->placeRepository = $placeRepository;
        $this->comparatorHandler = $comparatorHandler;
    }

    public function supports(string $resourceClass): bool
    {
        return PlaceDto::class === $resourceClass;
    }

    public function prefetchEntities(array $dtos): void
    {
        $places = $this->placeRepository->findAllByDtos($dtos);
        foreach ($places as $place) {
            $this->addEntity($place);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        $comparator = $this->comparatorHandler->getComparator($dto);
        $matching = $comparator->getMostMatching($this->places, $dto);

        if (null !== $matching && $matching->getConfidence() >= 90.0) {
            return $matching->getEntity();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity): void
    {
        $this->places[] = $entity;
    }

    public function clear(): void
    {
        unset($this->places); // Call GC
        $this->places = [];
    }
}
