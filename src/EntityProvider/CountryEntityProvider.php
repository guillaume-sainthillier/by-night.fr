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
use App\Dto\CountryDto;
use App\Entity\Country;
use App\Repository\CountryRepository;

class CountryEntityProvider implements EntityProviderInterface
{
    private CountryRepository $countryRepository;

    /** @var Country[] */
    private array $countries = [];

    public function __construct(CountryRepository $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    public function supports(string $dtoClassName): bool
    {
        return CountryDto::class === $dtoClassName;
    }

    public function prefetchEntities(array $dtos): void
    {
        $perIds = [];
        $perNames = [];

        /** @var CountryDto $dto */
        foreach ($dtos as $dto) {
            if (null !== $dto->code) {
                $perIds[$dto->code] = true;
            } elseif (null !== $dto->name) {
                $perNames[$dto->name] = true;
            }
        }

        $countries = $this->countryRepository->findAllByIdsOrNames(array_keys($perIds), array_keys($perNames));
        foreach ($countries as $country) {
            $this->addEntity($country);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param CountryDto $dto
     */
    public function getEntity(object $dto): ?object
    {
        foreach ($this->countries as $country) {
            if ($country->getId() === $dto->code) {
                return $country;
            }

            if ($country->getName() === $dto->name) {
                return $country;
            }

            if ($country->getDisplayName() === $dto->name) {
                return $country;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     *
     * @param Country $entity
     */
    public function addEntity(object $entity): void
    {
        //dump(__METHOD__, $entity);
        $this->countries[] = $entity;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): void
    {
        //dump(__METHOD__);
        unset($this->countries); // Call GC
        $this->countries = [];
    }
}
