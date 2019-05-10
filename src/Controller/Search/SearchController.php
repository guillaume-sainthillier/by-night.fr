<?php

namespace App\Controller\Search;

use App\Search\SearchEvent;
use App\SearchRepository\EventRepository;
use App\SearchRepository\UserElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    private function searchEvents(RepositoryManagerInterface $rm, $q)
    {
        /** @var EventRepository $repoSearch */
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

    /**
     * @Route("/", name="app_search_query")
     *
     * @param Request $request
     * @param RepositoryManagerInterface $rm
     * @param PaginatorInterface $paginator
     *
     * @return Response
     */
    public function searchAction(Request $request, RepositoryManagerInterface $rm, PaginatorInterface $paginator)
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

        $nbSoirees = 0;
        $events = [];
        $nbUsers = 0;
        $users = [];

        if ($q) {
            if (!$type || 'evenements' === $type) { //Recherche d'événements
                $query = $this->searchEvents($rm, $q);
                $pagination = $paginator->paginate($query, $page, $maxItems);
                $nbSoirees = $pagination->getTotalItemCount();
                $events = $pagination;

                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_events.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'maxItems' => $maxItems,
                        'page' => $page,
                        'events' => $events,
                    ]);
                }
            }

            if (!$type || 'membres' === $type) { //Recherche de membres
                $query = $this->searchUsers($rm, $q);
                $pagination = $paginator->paginate($query, $page, $maxItems);
                $nbUsers = $pagination->getTotalItemCount();
                $users = $pagination;

                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_users.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'maxItems' => $maxItems,
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
            'maxItems' => $maxItems,
            'events' => $events,
            'nbEvents' => $nbSoirees,
            'users' => $users,
            'nbUsers' => $nbUsers,
        ]);
    }
}
