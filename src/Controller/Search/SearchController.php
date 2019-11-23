<?php

namespace App\Controller\Search;

use App\Search\SearchEvent;
use App\SearchRepository\EventElasticaRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    /**
     * @Route("/", name="app_search_query")
     *
     * @return Response
     */
    public function searchAction(Request $request, PaginatorInterface $paginator, RepositoryManagerInterface $rm)
    {
        $q = \trim($request->get('q', null));
        $type = $request->get('type', null);
        $page = (int)($request->get('page', 1));
        $maxItems = 20;

        if ($page <= 0) {
            $page = 1;
        }

        if ($type && !\in_array($type, ['evenements', 'membres'])) {
            $type = null;
        }

        $events = $paginator->paginate([]);
        $users = $paginator->paginate([]);
        if ($q) {
            if (!$type || 'evenements' === $type) { //Recherche d'événements
                $results = $this->searchEvents($rm, $q);
                $events = $paginator->paginate($results, $page, $maxItems);

                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_events.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'page' => $page,
                        'events' => $events,
                    ]);
                }
            }

            if (!$type || 'membres' === $type) { //Recherche de membres
                $results = $this->searchUsers($rm, $q);
                $users = $paginator->paginate($results, $page, $maxItems);

                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_users.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'page' => $page,
                        'users' => $users,
                    ]);
                }
            }
        }

        return $this->render('Search/index.html.twig', [
            'term' => $q,
            'type' => $type,
            'page' => $page,
            'events' => $events,
            'users' => $users,
        ]);
    }

    private function searchEvents(RepositoryManagerInterface $rm, $q)
    {
        /** @var EventElasticaRepository $repoSearch */
        $repoSearch = $rm->getRepository('App:Event');
        $search = (new SearchEvent())->setTerm($q);

        return $repoSearch->findWithSearch($search, true);
    }

    private function searchUsers(RepositoryManagerInterface $rm, $q)
    {
        /** @var UserElasticaRepository $repo */
        $repo = $rm->getRepository('App:User');

        return $repo->findWithSearch($q);
    }
}
