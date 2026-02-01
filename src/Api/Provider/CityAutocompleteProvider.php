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
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Api\ApiResource\CityAutocomplete;
use App\Api\Pagination\TransformedPaginator;
use App\Entity\City;
use App\SearchRepository\CityElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * @implements ProviderInterface<CityAutocomplete>
 */
final readonly class CityAutocompleteProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return PaginatorInterface<CityAutocomplete>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PaginatorInterface
    {
        $limit = $this->pagination->getLimit($operation, $context);
        $page = $this->pagination->getPage($context);

        $term = trim((string) ($context['filters']['q'] ?? ''));
        if ('' === $term) {
            return new TransformedPaginator(
                items: [],
                totalItems: 0,
                currentPage: $page,
                itemsPerPage: $limit,
            );
        }

        /** @var CityElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(City::class);
        $results = $repo->findWithSearch($term);

        $results->setMaxPerPage($limit);
        $results->setCurrentPage($page);

        $output = [];
        /** @var City $city */
        foreach ($results as $city) {
            $output[] = new CityAutocomplete(
                slug: $city->getSlug(),
                name: $city->getFullName(),
            );
        }

        return new TransformedPaginator(
            items: $output,
            totalItems: $results->getNbResults(),
            currentPage: $page,
            itemsPerPage: $limit,
        );
    }
}
