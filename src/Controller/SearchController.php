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
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/recherche')]
class SearchController extends AbstractController
{
    #[Route(path: '/', name: 'app_search_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator, RepositoryManagerInterface $rm): Response
    {
        $q = trim($request->get('q'));
        $type = $request->get('type');
        $page = (int) ($request->get('page', 1));
        $maxItems = 20;
        if ($page <= 0) {
            $page = 1;
        }
        if ($type && !\in_array($type, ['evenements', 'membres'])) {
            $type = null;
        }
        $events = $paginator->paginate([]);
        $users = $paginator->paginate([]);
        if ('' !== $q) {
            if (!$type || 'evenements' === $type) { // Recherche d'événements
                $results = $this->searchEvents($rm, $q);
                $events = $paginator->paginate($results, $page, $maxItems);

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
                $results = $this->searchUsers($rm, $q);
                $users = $paginator->paginate($results, $page, $maxItems);

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

    private function searchEvents(RepositoryManagerInterface $rm, ?string $q)
    {
        /** @var EventElasticaRepository $repoSearch */
        $repoSearch = $rm->getRepository(Event::class);
        $search = (new SearchEvent())->setTerm($q);

        return $repoSearch->findWithSearch($search, true);
    }

    private function searchUsers(RepositoryManagerInterface $rm, ?string $q)
    {
        /** @var UserElasticaRepository $repo */
        $repo = $rm->getRepository(User::class);

        return $repo->findWithSearch($q);
    }
}
