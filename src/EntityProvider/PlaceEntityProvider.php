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
use App\Dto\PlaceDto;
use App\Entity\City;
use App\Entity\Place;
use App\Repository\PlaceRepository;
use App\Utils\SluggerUtils;

class PlaceEntityProvider implements EntityProviderInterface
{
    private PlaceRepository $placeRepository;

    /** @var Place[] */
    private array $places = [];

    public function __construct(PlaceRepository $placeRepository)
    {
        $this->placeRepository = $placeRepository;
    }

    public function supports(string $dtoClassName): bool
    {
        return PlaceDto::class === $dtoClassName;
    }

    public function prefetchEntities(array $dtos): void
    {
        dd($dtos);
    }

    /**
     * {@inheritDoc}
     *
     * @param CityDto $dto
     */
    public function getEntity(object $dto): ?object
    {
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
     *
     * @param City $entity
     */
    public function addEntity(object $entity): void
    {
        $this->cities[] = $entity;
    }

    public function clear(): void
    {
        unset($this->places); // Call GC
        $this->places = [];
    }
}
