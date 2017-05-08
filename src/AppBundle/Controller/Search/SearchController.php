<?php

namespace AppBundle\Controller\Search;

use AppBundle\SearchRepository\AgendaRepository;
use AppBundle\SearchRepository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use Symfony\Component\Routing\Annotation\Route;
use Pagerfanta\Pagerfanta;

use AppBundle\Entity\Site;
use AppBundle\Search\SearchAgenda;


class SearchController extends Controller
{
    /**
     * @Route("/evenements", name="tbn_old_search_query")
     */
    public function oldSearchAction()
    {
        $params = [
            "type" => "evenements"
        ];

        $term = $this->container->get('request_stack')->getCurrentRequest()->get('q');
        $page = $this->container->get('request_stack')->getCurrentRequest()->get('page');
        if ($term) {
            $params["q"] = $term;
        }

        if ($page) {
            $params['page'] = $page;
        }

        return new RedirectResponse($this->get("router")->generate("tbn_search_query", $params));
    }

    /**
     * @param RepositoryManager $rm
     * @param string $q
     * @return Pagerfanta
     */
    private function searchEvents(RepositoryManager $rm, $q)
    {
        /**
         * @var AgendaRepository $repoSearch
         */
        $repoSearch = $rm->getRepository("AppBundle:Agenda");
        $search = (new SearchAgenda())->setTerm($q);

        return $repoSearch->findWithSearch($search);
    }

    /**
     *
     * @param RepositoryManager $rm
     * @param string $q
     * @return Pagerfanta
     */
    private function searchUsers(RepositoryManager $rm, $q)
    {
        /**
         * @var UserRepository $repo
         */
        $repo = $rm->getRepository("AppBundle:User");

        return $repo->findWithSearch($q);
    }

    /**
     * @Route("/", name="tbn_search_query")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAction(Request $request)
    {
        $q = trim($request->get('q', null));
        $type = $request->get('type', null);
        $page = intval($request->get('page', 1));
        $rm = $this->get('fos_elastica.manager');
        $maxItems = 20;

        if ($page <= 0) {
            $page = 1;
        }

        if ($type && !in_array($type, ['evenements', 'membres'])) {
            $type = null;
        }

        $nbSoirees = 0;
        $soirees = [];
        $nbUsers = 0;
        $users = [];

        if ($q) {
            if (!$type || $type === 'evenements') //Recherche d'événements
            {
                $query = $this->searchEvents($rm, $q);
                $paginator = $this->get('knp_paginator');
                $pagination = $paginator->paginate($query, $page, $maxItems);
                $nbSoirees = $pagination->getTotalItemCount();
                $soirees = $pagination;


                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_events.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'maxItems' => $maxItems,
                        'page' => $page,
                        'events' => $soirees
                    ]);
                }
            }

            if (!$type || $type === 'membres') //Recherche de membres
            {
                $query = $this->searchUsers($rm, $q);
                $paginator = $this->get('knp_paginator');
                $pagination = $paginator->paginate($query, $page, $maxItems);
                $nbUsers = $pagination->getTotalItemCount();
                $users = $pagination;

                if ($request->isXmlHttpRequest()) {
                    return $this->render('Search/content_users.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'maxItems' => $maxItems,
                        'page' => $page,
                        'users' => $users
                    ]);
                }
            }
        }

        return $this->render("Search/index.html.twig", [
            "term" => $q,
            "type" => $type,
            "page" => $page,
            "maxItems" => $maxItems,
            "events" => $soirees,
            "nbEvents" => $nbSoirees,
            "users" => $users,
            "nbUsers" => $nbUsers
        ]);
    }
}
