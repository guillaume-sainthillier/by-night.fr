<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use FOS\ElasticaBundle\Manager\RepositoryManager;
use Pagerfanta\Pagerfanta;

use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Search\SearchAgenda;


class SearchController extends Controller
{

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

        if($page) {
            $params['page'] = $page;
        }

        return new RedirectResponse($this->get("router")->generate("tbn_search_query", $params));
    }

    /**
     *
     * @param RepositoryManager $rm
     * @param Site $site
     * @param string $q
     * @return Pagerfanta
     */
    private function searchEvents(RepositoryManager $rm, Site $site, $q)
    {
        $repoSearch = $rm->getRepository("TBNAgendaBundle:Agenda");
        $search = (new SearchAgenda())->setTerm($q);

        return $repoSearch->findWithSearch($site, $search); //100ms
    }

    /**
     *
     * @param RepositoryManager $rm
     * @param Site $site
     * @param string $q
     * @return Pagerfanta
     */
    private function searchUsers(RepositoryManager $rm, Site $site, $q)
    {
        $repo = $rm->getRepository("TBNUserBundle:User");

        return $repo->findWithSearch($site, $q);
    }

    public function searchAction(Request $request)
    {
        $q = trim($request->get('q', null));
        $type = $request->get('type', null);
        $page = intval($request->get('page', 1));
        $rm = $this->get('fos_elastica.manager');
        $siteManager = $this->get("site_manager");
        $site = $siteManager->getCurrentSite();
        $maxItems = 20;

        if ($page <= 0) {
            $page = 1;
        }

        $nbSoirees = 0;
        $soirees = [];
        $nbUsers = 0;
        $users = [];

        if ($q) {
            if (!$type || $type === 'evenements') //Recherche d'événements
            {
                $results = $this->searchEvents($rm, $site, $q);
                $results->setMaxPerPage($maxItems)->setCurrentPage($page);

                $soirees = $results->getCurrentPageResults();
                $nbSoirees = $results->getNbResults();

                if ($request->isXmlHttpRequest()) {
                    return $this->render('TBNMainBundle:Search:content_events.html.twig', [
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
                $results = $this->searchUsers($rm, $site, $q);
                $results->setMaxPerPage($maxItems)->setCurrentPage($page);

                $users = $results->getCurrentPageResults();
                $nbUsers = $results->getNbResults();

                if ($request->isXmlHttpRequest()) {
                    return $this->render('TBNMainBundle:Search:content_users.html.twig', [
                        'type' => $type,
                        'term' => $q,
                        'maxItems' => $maxItems,
                        'page' => $page,
                        'users' => $soirees
                    ]);
                }
            }
        }

        return $this->render("TBNMainBundle:Search:search.html.twig", [
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
