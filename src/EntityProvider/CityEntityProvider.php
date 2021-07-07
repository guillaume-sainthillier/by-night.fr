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
use App\Dto\CityDto;
use App\Handler\ComparatorHandler;
use App\Repository\CityRepository;

class CityEntityProvider extends AbstractEntityProvider
{
    private CityRepository $cityRepository;

    private ComparatorHandler $comparatorHandler;

    public function __construct(CityRepository $cityRepository, ComparatorHandler $comparatorHandler)
    {
        $this->cityRepository = $cityRepository;
        $this->comparatorHandler = $comparatorHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass): bool
    {
        return CityDto::class === $resourceClass;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        $comparator = $this->comparatorHandler->getComparator($dto);
        $matching = $comparator->getMostMatching($this->entities, $dto);

        if (null !== $matching && $matching->getConfidence() >= 100.0) {
            return $matching->getEntity();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(): DtoFindableRepositoryInterface
    {
        return $this->cityRepository;
    }
}
