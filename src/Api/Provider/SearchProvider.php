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
use ApiPlatform\State\ProviderInterface;
use App\Api\ApiResource\SearchResult;
use App\Api\Pagination\ArrayPaginator;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Tag;
use App\Entity\User;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\TagElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\HybridResult;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @implements ProviderInterface<SearchResult>
 */
final readonly class SearchProvider implements ProviderInterface
{
    public function __construct(
        private RepositoryManagerInterface $repositoryManager,
        private UrlGeneratorInterface $urlGenerator,
        private Pagination $pagination,
    ) {
    }

    /**
     * @return iterable<SearchResult>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        $query = trim($context['filters']['q'] ?? '');
        if ('' === $query) {
            return [];
        }

        $limit = $this->pagination->getLimit($operation, $context);
        $page = $this->pagination->getPage($context);

        // Divide limit by 4 to get items per type (events, cities, users, tags)
        $itemsPerType = max(1, (int) ceil($limit / 4));

        // Get paginated hybrid results from each repository
        $eventsPaginator = $this->getEventsPaginator($query, $page, $itemsPerType);
        $citiesPaginator = $this->getCitiesPaginator($query, $page, $itemsPerType);
        $usersPaginator = $this->getUsersPaginator($query, $page, $itemsPerType);
        $tagsPaginator = $this->getTagsPaginator($query, $page, $itemsPerType);

        // Transform and combine results
        $results = [
            ...$this->transformEventResults($eventsPaginator),
            ...$this->transformCityResults($citiesPaginator),
            ...$this->transformUserResults($usersPaginator),
            ...$this->transformTagResults($tagsPaginator),
        ];

        // Calculate total items across all types
        $totalItems = $eventsPaginator->getNbResults()
            + $citiesPaginator->getNbResults()
            + $usersPaginator->getNbResults()
            + $tagsPaginator->getNbResults();

        /* @var ArrayPaginator<SearchResult> */
        return new ArrayPaginator(
            items: $results,
            totalItems: $totalItems,
            currentPage: $page,
            itemsPerPage: $limit,
        );
    }

    /**
     * @return PagerfantaInterface<HybridResult<Event>>
     */
    private function getEventsPaginator(string $query, int $page, int $itemsPerType): PagerfantaInterface
    {
        /** @var EventElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(Event::class);
        $paginator = $repo->findWithHighlightsPaginated($query);
        $paginator->setMaxPerPage($itemsPerType);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @return PagerfantaInterface<HybridResult<City>>
     */
    private function getCitiesPaginator(string $query, int $page, int $itemsPerType): PagerfantaInterface
    {
        /** @var CityElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(City::class);
        $paginator = $repo->findWithHighlightsPaginated($query);
        $paginator->setMaxPerPage($itemsPerType);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @return PagerfantaInterface<HybridResult<User>>
     */
    private function getUsersPaginator(string $query, int $page, int $itemsPerType): PagerfantaInterface
    {
        /** @var UserElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(User::class);
        $paginator = $repo->findWithHighlightsPaginated($query);
        $paginator->setMaxPerPage($itemsPerType);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @return PagerfantaInterface<HybridResult<Tag>>
     */
    private function getTagsPaginator(string $query, int $page, int $itemsPerType): PagerfantaInterface
    {
        /** @var TagElasticaRepository $repo */
        $repo = $this->repositoryManager->getRepository(Tag::class);
        $paginator = $repo->findWithHighlightsPaginated($query);
        $paginator->setMaxPerPage($itemsPerType);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @param PagerfantaInterface<HybridResult<Event>> $paginator
     *
     * @return list<SearchResult>
     */
    private function transformEventResults(PagerfantaInterface $paginator): array
    {
        $results = [];
        foreach ($paginator as $result) {
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
                    'id' => $event->getId(),
                    'location' => $event->getLocationSlug(),
                ]),
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
     * @param PagerfantaInterface<HybridResult<City>> $paginator
     *
     * @return list<SearchResult>
     */
    private function transformCityResults(PagerfantaInterface $paginator): array
    {
        $results = [];
        foreach ($paginator as $result) {
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
     * @param PagerfantaInterface<HybridResult<User>> $paginator
     *
     * @return list<SearchResult>
     */
    private function transformUserResults(PagerfantaInterface $paginator): array
    {
        $results = [];
        foreach ($paginator as $result) {
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

    /**
     * @param PagerfantaInterface<HybridResult<Tag>> $paginator
     *
     * @return list<SearchResult>
     */
    private function transformTagResults(PagerfantaInterface $paginator): array
    {
        $results = [];
        foreach ($paginator as $result) {
            /** @var Tag $tag */
            $tag = $result->getTransformed();
            $highlights = $result->getResult()->getHighlights();

            $results[] = new SearchResult(
                id: 'tag-' . $tag->getId(),
                type: 'tags',
                category: 'Catégories',
                label: $tag->getName(),
                shortDescription: '',
                description: null,
                url: $this->urlGenerator->generate('app_agenda_by_tag', [
                    'location' => 'c--france',
                    'tagSlug' => $tag->getSlug(),
                    'tagId' => $tag->getId(),
                ]),
                highlightResult: [
                    'label' => [
                        'value' => $highlights['name'][0] ?? $tag->getName(),
                    ],
                ],
            );
        }

        return $results;
    }
}
