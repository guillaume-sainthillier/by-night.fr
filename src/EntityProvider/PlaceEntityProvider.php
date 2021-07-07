<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\PlaceDto;
use App\Handler\ComparatorHandler;
use App\Repository\PlaceRepository;

class PlaceEntityProvider extends AbstractEntityProvider
{
    private PlaceRepository $placeRepository;

    private ComparatorHandler $comparatorHandler;

    public function __construct(PlaceRepository $placeRepository, ComparatorHandler $comparatorHandler)
    {
        $this->placeRepository = $placeRepository;
        $this->comparatorHandler = $comparatorHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass): bool
    {
        return PlaceDto::class === $resourceClass;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(): DtoFindableRepositoryInterface
    {
        return $this->placeRepository;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        $comparator = $this->comparatorHandler->getComparator($dto);
        $matching = $comparator->getMostMatching($this->entities, $dto);

        if (null !== $matching && $matching->getConfidence() >= 90.0) {
            return $matching->getEntity();
        }

        return null;
    }
}
