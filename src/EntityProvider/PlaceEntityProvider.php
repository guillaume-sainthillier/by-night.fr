<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\PlaceDto;
use App\Handler\ComparatorHandler;
use App\Repository\PlaceRepository;

final class PlaceEntityProvider extends AbstractEntityProvider
{
    public function __construct(
        private readonly PlaceRepository $placeRepository,
        private readonly ComparatorHandler $comparatorHandler,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return PlaceDto::class === $dtoClassName;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        $entity = parent::getEntity($dto);
        if (null !== $entity) {
            return $entity;
        }

        $comparator = $this->comparatorHandler->getComparator($dto);
        $matching = $comparator->getMostMatching($this->getEntities(), $dto);

        if (null !== $matching && $matching->getConfidence() >= 90.0) {
            return $matching->getEntity();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->placeRepository;
    }
}
