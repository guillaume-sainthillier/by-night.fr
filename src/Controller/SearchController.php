<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/recherche')]
class SearchController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    #[Route(path: '/', name: 'app_search_index', methods: ['GET'])]
    public function index(Request $request, RepositoryManagerInterface $rm): Response
    {
        $q = trim($request->query->get('q'));
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

    private function searchEvents(RepositoryManagerInterface $rm, ?string $query): PagerfantaInterface
    {
        /** @var EventElasticaRepository $repoSearch */
        $repoSearch = $rm->getRepository(Event::class);
        $search = (new SearchEvent())->setTerm($query);

        return $repoSearch->findWithSearch($search, true);
    }

    private function searchUsers(RepositoryManagerInterface $rm, ?string $query): PagerfantaInterface
    {
        /** @var UserElasticaRepository $repo */
        $repo = $rm->getRepository(User::class);

        return $repo->findWithSearch($query);
    }
}
