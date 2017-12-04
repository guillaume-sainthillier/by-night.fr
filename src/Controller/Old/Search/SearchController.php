<?php

namespace App\Controller\Old\Search;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class SearchController extends Controller
{
    /**
     * @Route("/evenements", name="tbn_old_search_query")
     */
    public function oldSearchAction(Request $request)
    {
        $params = [
            'type' => 'evenements',
        ];


        $term = $request->get('q');
        $page = $request->get('page');
        if ($term) {
            $params['q'] = $term;
        }

        if ($page) {
            $params['page'] = $page;
        }

        return $this->redirectToRoute('tbn_search_query', $params);
    }
}
