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
use App\Api\ApiResource\SearchResult;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements ProviderInterface<SearchResult>
 */
final readonly class SearchProvider implements ProviderInterface
{
    private const int MAX_RESULTS_PER_TYPE = 5;

    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<SearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $query = trim($context['filters']['q'] ?? '');
        if ('' === $query) {
            return [];
        }

        return [
            ...$this->searchEvents($query),
            ...$this->searchCities($query),
            ...$this->searchUsers($query),
        ];
    }

    /**
     * @return list<SearchResult>
     */
    private function searchEvents(string $query): array
    {
        /** @var EventElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(Event::class);
        $eventResults = $repo->findWithHighlights($query, self::MAX_RESULTS_PER_TYPE);

        $results = [];
        foreach ($eventResults as $result) {
            /** @var Event $event */
            $event = $result->getTransformed();
            $highlights = $result->getResult()->getHighlights();

            $results[] = new SearchResult(
                id: 'event-' . $event->getId(),
                type: 'events',
                category: 'Événements',
                label: $event->getName(),
                shortDescription: $event->getPlace()?->getName() ?? '',
                description: $event->getStartDate()?->format('d/m/Y'),
                url: $this->urlGenerator->generate('app_event_details', [
                    'slug' => $event->getSlug(),
                    'location' => $event->getPlace()?->getCity()?->getSlug() ?? 'france',
                ]),
                icon: 'fa fa-masks-theater',
                highlightResult: [
                    'label' => [
                        'value' => $highlights['name'][0] ?? $event->getName(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['place.name'][0] ?? ($event->getPlace()?->getName() ?? ''),
                    ],
                ],
            );
        }

        return $results;
    }

    /**
     * @return list<SearchResult>
     */
    private function searchCities(string $query): array
    {
        /** @var CityElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(City::class);
        $cityResults = $repo->findWithHighlights($query, self::MAX_RESULTS_PER_TYPE);

        $results = [];
        foreach ($cityResults as $result) {
            /** @var City $city */
            $city = $result->getTransformed();
            $highlights = $result->getResult()->getHighlights();

            $results[] = new SearchResult(
                id: 'city-' . $city->getId(),
                type: 'cities',
                category: 'Villes',
                label: $city->getName(),
                shortDescription: $city->getCountry()?->getName() ?? '',
                description: \sprintf('%s habitants', number_format($city->getPopulation() ?? 0, 0, ',', ' ')),
                url: $this->urlGenerator->generate('app_location_index', ['location' => $city->getSlug()]),
                icon: 'fa fa-location-crosshairs',
                highlightResult: [
                    'label' => [
                        'value' => $highlights['name'][0] ?? $city->getName(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['country.name'][0] ?? ($city->getCountry()?->getName() ?? ''),
                    ],
                ],
            );
        }

        return $results;
    }

    /**
     * @return list<SearchResult>
     */
    private function searchUsers(string $query): array
    {
        /** @var UserElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(User::class);
        $userResults = $repo->findWithHighlights($query, self::MAX_RESULTS_PER_TYPE);

        $results = [];
        foreach ($userResults as $result) {
            /** @var User $user */
            $user = $result->getTransformed();
            $highlights = $result->getResult()->getHighlights();

            $fullName = $user->getFirstname() && $user->getLastname()
                ? $user->getFirstname() . ' ' . $user->getLastname()
                : '';

            $results[] = new SearchResult(
                id: 'user-' . $user->getId(),
                type: 'users',
                category: 'Membres',
                label: $user->getUsername(),
                shortDescription: $fullName,
                description: \sprintf('%d événement(s)', $user->getUserEvents()->count()),
                url: $this->urlGenerator->generate('app_user_index', ['slug' => $user->getSlug(), 'id' => $user->getId()]),
                icon: 'fa fa-user',
                highlightResult: [
                    'label' => [
                        'value' => $highlights['username'][0] ?? $user->getUsername(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['firstname'][0] ?? $highlights['lastname'][0] ?? $fullName,
                    ],
                ],
            );
        }

        return $results;
    }
}
