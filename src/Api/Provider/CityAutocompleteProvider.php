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
use ApiPlatform\State\ProviderInterface;
use App\Api\ApiResource\CityAutocomplete;
use App\Entity\City;
use App\SearchRepository\CityElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * @implements ProviderInterface<CityAutocomplete>
 */
final readonly class CityAutocompleteProvider implements ProviderInterface
{
    private const int MAX_RESULTS = 7;

    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
    ) {
    }

    /**
     * @return list<CityAutocomplete>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $term = trim((string) ($operation->getParameters()?->get('q')?->getValue() ?? ''));
        if ('' === $term) {
            return [];
        }

        /** @var CityElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(City::class);
        $results = $repo->findWithSearch($term);
        $results->setMaxPerPage(self::MAX_RESULTS);
        $results->setCurrentPage(1);

        $output = [];
        /** @var City $city */
        foreach ($results as $city) {
            $output[] = new CityAutocomplete(
                slug: $city->getSlug(),
                name: $city->getFullName(),
            );
        }

        return $output;
    }
}
