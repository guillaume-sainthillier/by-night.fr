<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Api\ApiResource\CityAutocomplete;
use App\Api\Input\QueryInputMapper;
use App\Api\Model\CitySearchInput;
use App\Api\Model\CitySearchResult;
use App\Entity\City;
use App\SearchRepository\CityElasticaRepository;
use Closure;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;

/**
 * The cities a name or a postal code matches, for the autocomplete of the site's city fields (GET /api/cities) and the
 * MCP tool find_cities.
 *
 * @extends AbstractElasticaAutocompleteProvider<CityAutocomplete>
 */
final readonly class CityAutocompleteProvider extends AbstractElasticaAutocompleteProvider
{
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        Pagination $pagination,
        private QueryInputMapper $inputMapper,
    ) {
        parent::__construct($repositoryManager, $pagination);
    }

    /**
     * @return iterable<CityAutocomplete>|CitySearchResult
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
    {
        $cities = parent::provide($operation, $uriVariables, $context);

        return isset($context['mcp_request']) ? new CitySearchResult(array_values(\is_array($cities) ? $cities : iterator_to_array($cities, false))) : $cities;
    }

    protected function getTerm(Operation $operation, array $context): string
    {
        return trim($this->inputMapper->map($operation, $context, CitySearchInput::class)->q);
    }

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
            upcomingEvents: $city->getUpcomingEvents(),
        );
    }
}
