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
use App\Dto\CityDto;
use App\Entity\City;
use App\Repository\CityRepository;
use App\Utils\SluggerUtils;

class CityEntityProvider implements EntityProviderInterface
{
    private CityRepository $cityRepository;

    /** @var City[] */
    private array $cities = [];

    public function __construct(CityRepository $cityRepository)
    {
        $this->cityRepository = $cityRepository;
    }

    public function supports(string $resourceClass): bool
    {
        return CityDto::class === $resourceClass;
    }

    public function prefetchEntities(array $dtos): void
    {
        $namesAndCountries = [];
        /** @var CityDto $dto */
        foreach ($dtos as $dto) {
            if (null === $dto->name || null === $dto->country || null === $dto->country->id) {
                continue;
            }

            $key = sprintf('%s-%s', $dto->name, $dto->country->id);
            $namesAndCountries[$key] = [$dto->name, $dto->country->id];
        }

        $cities = $this->cityRepository->findAllByNamesAndCountries($namesAndCountries);

        foreach ($cities as $city) {
            $this->addEntity($city);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getEntity(object $dto): ?object
    {
        \assert($dto instanceof CityDto);

        foreach ($this->cities as $city) {
            if ($dto->country->id !== $city->getCountry()->getId()) {
                continue;
            }

            if (SluggerUtils::generateSlug($dto->name) === $city->getSlug()) {
                return $city;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function addEntity(object $entity): void
    {
        \assert($entity instanceof City);

        $this->cities[] = $entity;
    }

    public function clear(): void
    {
        unset($this->cities); // Call GC
        $this->cities = [];
    }
}
