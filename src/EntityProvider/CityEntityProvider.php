<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityProvider;

use App\Contracts\DtoFindableRepositoryInterface;
use App\Dto\CityDto;
use App\Handler\ComparatorHandler;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;

class CityEntityProvider extends AbstractEntityProvider
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private CityRepository $cityRepository,
        private ComparatorHandler $comparatorHandler
    ) {
        parent::__construct($entityManager);
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return CityDto::class === $dtoClassName;
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

        if (null !== $matching && $matching->getConfidence() >= 100.0) {
            return $matching->getEntity();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function getRepository(string $dtoClassName): DtoFindableRepositoryInterface
    {
        return $this->cityRepository;
    }
}
