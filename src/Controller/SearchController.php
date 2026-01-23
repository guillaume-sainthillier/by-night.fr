<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\User;
use App\Search\SearchEvent;
use App\SearchRepository\CityElasticaRepository;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/recherche')]
final class SearchController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    #[Route(path: '/api', name: 'app_search_api', methods: ['GET'])]
    public function api(Request $request, RepositoryManagerInterface $rm): JsonResponse
    {
        $query = trim($request->query->get('query') ?? '');
        $results = [];

        if ('' === $query) {
            return new JsonResponse($results);
        }

        // Search events with Elasticsearch highlighting
        /** @var EventElasticaRepository $eventRepo */
        $eventRepo = $rm->getRepository(Event::class);
        $eventResults = $eventRepo->findWithHighlights($query, 5);

        foreach ($eventResults as $result) {
            $event = $result->getTransformed();
            $highlights = $result->getHighlights();

            $results[] = [
                'id' => 'event-' . $event->getId(),
                'type' => 'events',
                'category' => 'Événements',
                'label' => $event->getName(),
                'shortDescription' => $event->getPlace()?->getName() ?? '',
                'description' => $event->getStartDate()?->format('d/m/Y'),
                'url' => $this->generateUrl('app_event_details', [
                    'slug' => $event->getSlug(),
                    'location' => $event->getPlace()?->getCity()?->getSlug() ?? 'france',
                ]),
                'icon' => 'fa fa-masks-theater',
                '_highlightResult' => [
                    'label' => [
                        'value' => $highlights['name'][0] ?? $event->getName(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['place.name'][0] ?? ($event->getPlace()?->getName() ?? ''),
                    ],
                ],
            ];
        }

        // Search cities with Elasticsearch highlighting
        /** @var CityElasticaRepository $cityRepo */
        $cityRepo = $rm->getRepository(City::class);
        $cityResults = $cityRepo->findWithHighlights($query, 5);

        foreach ($cityResults as $result) {
            $city = $result->getTransformed();
            $highlights = $result->getHighlights();

            $results[] = [
                'id' => 'city-' . $city->getId(),
                'type' => 'cities',
                'category' => 'Villes',
                'label' => $city->getName(),
                'shortDescription' => $city->getCountry()?->getName() ?? '',
                'description' => \sprintf('%s habitants', number_format($city->getPopulation() ?? 0, 0, ',', ' ')),
                'url' => $this->generateUrl('app_location_index', ['location' => $city->getSlug()]),
                'icon' => 'fa fa-location-crosshairs',
                '_highlightResult' => [
                    'label' => [
                        'value' => $highlights['name'][0] ?? $city->getName(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['country.name'][0] ?? ($city->getCountry()?->getName() ?? ''),
                    ],
                ],
            ];
        }

        // Search users with Elasticsearch highlighting
        /** @var UserElasticaRepository $userRepo */
        $userRepo = $rm->getRepository(User::class);
        $userResults = $userRepo->findWithHighlights($query, 5);

        foreach ($userResults as $result) {
            $user = $result->getTransformed();
            $highlights = $result->getHighlights();

            $fullName = $user->getFirstname() && $user->getLastname()
                ? $user->getFirstname() . ' ' . $user->getLastname()
                : '';

            $results[] = [
                'id' => 'user-' . $user->getId(),
                'type' => 'users',
                'category' => 'Membres',
                'label' => $user->getUsername(),
                'shortDescription' => $fullName,
                'description' => \sprintf('%d événement(s)', $user->getUserEvents()->count()),
                'url' => $this->generateUrl('app_user_index', ['slug' => $user->getSlug(), 'id' => $user->getId()]),
                'icon' => 'fa fa-user',
                '_highlightResult' => [
                    'label' => [
                        'value' => $highlights['username'][0] ?? $user->getUsername(),
                    ],
                    'shortDescription' => [
                        'value' => $highlights['firstname'][0] ?? $highlights['lastname'][0] ?? $fullName,
                    ],
                ],
            ];
        }

        return new JsonResponse($results);
    }

    #[Route(path: '/', name: 'app_search_index', methods: ['GET'])]
    public function index(Request $request, RepositoryManagerInterface $rm): Response
    {
        $q = trim($request->query->get('q') ?? '');
        $type = $request->query->get('type');
        $page = max($request->query->getInt('page'), 1);

        if ($type && !\in_array($type, ['evenements', 'membres'], true)) {
            $type = null;
        }

        $events = $this->createEmptyPaginator($page, self::ITEMS_PER_PAGE);
        $users = $this->createEmptyPaginator($page, self::ITEMS_PER_PAGE);
        if ('' !== $q) {
            if (!$type || 'evenements' === $type) { // Recherche d'événements
                $events = $this->searchEvents($rm, $q);
                $this->updatePaginator($events, $page, self::ITEMS_PER_PAGE);

                if ($request->isXmlHttpRequest()) {
                    return $this->render('search/content-events.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'page' => $page,
                        'events' => $events,
                    ]);
                }
            }

            if (!$type || 'membres' === $type) { // Recherche de membres
                $users = $this->searchUsers($rm, $q);
                $this->updatePaginator($users, $page, self::ITEMS_PER_PAGE);

                if ($request->isXmlHttpRequest()) {
                    return $this->render('search/content-users.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'page' => $page,
                        'users' => $users,
                    ]);
                }
            }
        }

        return $this->render('search/index.html.twig', [
            'term' => $q,
            'type' => $type,
            'page' => $page,
            'events' => $events,
            'users' => $users,
        ]);
    }

    /**
     * @return PagerfantaInterface<Event>
     */
    private function searchEvents(RepositoryManagerInterface $rm, ?string $query): PagerfantaInterface
    {
        /** @var EventElasticaRepository $repoSearch */
        $repoSearch = $rm->getRepository(Event::class);
        $search = new SearchEvent()->setTerm($query);

        return $repoSearch->findWithSearch($search);
    }

    /**
     * @return PagerfantaInterface<User>
     */
    private function searchUsers(RepositoryManagerInterface $rm, ?string $query): PagerfantaInterface
    {
        /** @var UserElasticaRepository $repo */
        $repo = $rm->getRepository(User::class);

        return $repo->findWithSearch($query);
    }

    /**
     * @return PagerfantaInterface<City>
     */
    private function searchCities(RepositoryManagerInterface $rm, ?string $query): PagerfantaInterface
    {
        /** @var CityElasticaRepository $repo */
        $repo = $rm->getRepository(City::class);

        return $repo->findWithSearch($query);
    }
}
