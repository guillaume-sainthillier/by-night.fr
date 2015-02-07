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
        $soirees        = $repoSearch->findWithSearch($site, $search, $offset, $limit, false); //100ms
        $nbSoirees      = $repo->findCountWithSearch($site, $search); //10ms

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
    
    public function searchAction(Request $request, $type)
    {
        $q              = trim($request->get('q', null));
        $page           = intval($request->get('page', 1));
        $em             = $this->getDoctrine()->getManager();
        $siteManager    = $this->get("site_manager");
        $site           = $siteManager->getCurrentSite();
        $maxItems       = 20;

        if($page <= 0)
        {
            $page = 1;
        }

        if($type === 'evenements')
        {
            list($soirees, $nbSoirees) = $this->searchEvents($em, $site, $q, $page, $maxItems);
            return $this->render('TBNMainBundle:Search:events.html.twig', [
                "events"    => $soirees,
                "nbSoirees" => $nbSoirees,
                "term"      => $q,
                "maxItems"  => $maxItems,
                "page"      => $page
            ]);
        }

        if($type === 'membres')
        {
            list($users, $nbUsers) = $this->searchUsers($em, $site, $q, $page, $maxItems);
            return $this->render('TBNMainBundle:Search:users.html.twig', [
                "users"     => $users,
                "nbUsers"   => $nbUsers,
                "term"      => $q,
                "maxItems"  => $maxItems,
                "page"      => $page
            ]);
        }

        list($users, $nbUsers)      = $this->searchUsers($em, $site, $q, $page, $maxItems);
        list($soirees, $nbSoirees)  = $this->searchEvents($em, $site, $q, $page, $maxItems);
        
        return $this->render("TBNMainBundle:Search:list.html.twig", [
            "term"      => $q,
            "page"      => $page,
            "type"      => $type,
            "nbItems"   => ($nbSoirees + $nbUsers),
            "maxItems"  => $maxItems,
            "events"    => $soirees,
            "users"     => $users,
        ]);
    }
}
