<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/recherche')]
final class SearchController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    #[Route(path: '/', name: 'app_search_index', methods: ['GET'])]
    public function index(Request $request, RepositoryManagerInterface $rm, EventRepository $eventRepository, UserRepository $userRepository): Response
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
            if (!$type || 'evenements' === $type) { // Search for events
                $events = $this->searchEvents($rm, $eventRepository, $q, $page, self::ITEMS_PER_PAGE);

                if ($request->isXmlHttpRequest()) {
                    return $this->render('search/content-events.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'page' => $page,
                        'events' => $events,
                    ]);
                }
            }

            if (!$type || 'membres' === $type) { // Search for members
                $users = $this->searchUsers($rm, $userRepository, $q, $page, self::ITEMS_PER_PAGE);

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
    private function searchEvents(RepositoryManagerInterface $rm, EventRepository $eventRepository, ?string $query, int $page, int $limit): PagerfantaInterface
    {
        /** @var EventElasticaRepository $repoSearch */
        $repoSearch = $rm->getRepository(Event::class);
        $search = new SearchEvent()->setTerm($query);

        $adapter = $repoSearch->findWithSearch($search);

        return $this->createMultipleEagerLoadingPaginatorFromAdapter(
            $adapter,
            $eventRepository,
            $page,
            $limit,
            ['view' => 'events:search:list'],
        );
    }

    /**
     * @return PagerfantaInterface<User>
     */
    private function searchUsers(RepositoryManagerInterface $rm, UserRepository $userRepository, ?string $query, int $page, int $limit): PagerfantaInterface
    {
        /** @var UserElasticaRepository $repo */
        $repo = $rm->getRepository(User::class);

        $adapter = $repo->findWithSearch($query);

        return $this->createMultipleEagerLoadingPaginatorFromAdapter(
            $adapter,
            $userRepository,
            $page,
            $limit,
            ['view' => 'users:search:list'],
        );
    }
}
