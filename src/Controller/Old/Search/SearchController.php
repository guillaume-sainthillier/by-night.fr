<?php

namespace App\Controller\Old\Search;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

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

        $term = $this->container->get(RequestStack::class)->getCurrentRequest()->get('q');
        $page = $this->container->get(RequestStack::class)->getCurrentRequest()->get('page');
        if ($term) {
            $params['q'] = $term;
        }

        if ($page) {
            $params['page'] = $page;
        }

        return new RedirectResponse($this->get(RouterInterface::class)->generate('tbn_search_query', $params));
    }
}
