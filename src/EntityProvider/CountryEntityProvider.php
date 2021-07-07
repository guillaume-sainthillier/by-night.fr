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
use App\Dto\CountryDto;
use App\Handler\ComparatorHandler;
use App\Repository\CountryRepository;

class CountryEntityProvider extends AbstractEntityProvider
{
    private CountryRepository $countryRepository;

    private ComparatorHandler $comparatorHandler;

    public function __construct(CountryRepository $countryRepository, ComparatorHandler $comparatorHandler)
    {
        $this->countryRepository = $countryRepository;
        $this->comparatorHandler = $comparatorHandler;
    }

    public function supports(string $resourceClass): bool
    {
        return CountryDto::class === $resourceClass;
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
        return $this->countryRepository;
    }
}
