<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Search\SearchAgenda;

class SearchController extends Controller
{

    private function searchEvents($em, Site $site, $q, $offset, $limit)
    {
        if(!$q)
        {
            return [[], 0];
        }

        /** var \FOS\ElasticaBundle\Manager\RepositoryManager */
        $repositoryManager = $this->get('fos_elastica.manager');

	$repo           = $em->getRepository("TBNAgendaBundle:Agenda"); // 100ms
        $repoSearch     = $repositoryManager->getRepository("TBNAgendaBundle:Agenda");
        $search         = (new SearchAgenda())->setTerm($q);
        $results        = $repoSearch->findWithSearch($site, $search, $offset, $limit); //100ms
        $soirees        = $results->getCurrentPage();
        $nbSoirees      = $results->getNbResults(); //10ms

        return [$soirees, $nbSoirees];
    }

    private function searchUsers($em, Site $site, $q, $offset, $limit)
    {
        if(!$q)
        {
            return [[], 0];
        }

        $repo           = $em->getRepository("TBNUserBundle:User");
        $users          = $repo->search($site, $q);
        $nbUsers        = count($users); // Changer ça!!

        return [$users, $nbUsers];
    }
    
    public function searchAction(Request $request)
    {
        $q              = trim($request->get('q', null));
        $type           = $request->get('type', null);
        $page           = intval($request->get('page', 1));        
        $em             = $this->getDoctrine()->getManager();
        $siteManager    = $this->get("site_manager");
        $site           = $siteManager->getCurrentSite();
        $maxItems       = 20;

        if($page <= 0)
        {
            $page = 1;
        }

        $nbSoirees  = 0;
        $soirees    = [];
        $nbUsers    = 0;
        $users      = [];
        
        //$finder = $this->container->get('fos_elastica.finder.search.user');
        
        if(!$type || $type === 'evenements') //Recherche d'événements
        {
            list($soirees, $nbSoirees) = $this->searchEvents($em, $site, $q, $page, $maxItems);
        }

        if(!$type || $type === 'membres') //Recherche de membres
        {
            list($users, $nbUsers) = $this->searchUsers($em, $site, $q, $page, $maxItems);
        }
        
        var_dump($soirees->getNbResults());
        die();

        return $this->render("TBNMainBundle:Search:search.html.twig", [
            "term"      => $q,
            "type"      => $type,
            "page"      => $page,
            "maxItems"  => $maxItems,
            "events"    => $soirees,
            "nbEvents"  => $nbSoirees,
            "users"     => $users,
            "nbUsers"   => $nbUsers
        ]);
    }
}
