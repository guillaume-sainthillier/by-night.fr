<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use App\Api\ApiResource\CityAutocomplete;
use App\Entity\City;
use App\SearchRepository\CityElasticaRepository;
use Closure;
use Pagerfanta\PagerfantaInterface;

/**
 * @extends AbstractElasticaAutocompleteProvider<CityAutocomplete>
 */
final readonly class CityAutocompleteProvider extends AbstractElasticaAutocompleteProvider
{
    protected function search(string $term): PagerfantaInterface
    {
        /** @var CityElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(City::class);

        return $repo->findWithSearch($term);
    }

    protected function transformer(): Closure
    {
        return static fn (City $city): CityAutocomplete => new CityAutocomplete(
            slug: $city->getSlug(),
            name: $city->getFullName(),
        );
    }
}
