<?php

namespace AppBundle\Controller\Old\Search;

use AppBundle\SearchRepository\AgendaRepository;
use AppBundle\SearchRepository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use Symfony\Component\Routing\Annotation\Route;
use Pagerfanta\Pagerfanta;


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
}
