<?php

namespace AppBundle\Controller\Old\Search;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends Controller
{
    /**
     * @Route("/evenements", name="tbn_old_search_query")
     */
    public function oldSearchAction()
    {
        $params = [
            'type' => 'evenements',
        ];

        $term = $this->container->get('request_stack')->getCurrentRequest()->get('q');
        $page = $this->container->get('request_stack')->getCurrentRequest()->get('page');
        if ($term) {
            $params['q'] = $term;
        }

        if ($page) {
            $params['page'] = $page;
        }

        return new RedirectResponse($this->get('router')->generate('tbn_search_query', $params));
    }
}
