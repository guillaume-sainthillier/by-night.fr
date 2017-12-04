<?php

namespace App\Controller\City;

use App\Entity\City;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/recherche")
 */
class SearchController extends Controller
{
    /**
     * @Route("/", name="tbn_search_query_city", requirements={"city": ".+"})
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function searchAction(City $city, Request $request)
    {
        $params = [];

        $term = $request->get('q');
        if ($term) {
            $params['q'] = $term;
        }

        $page = $request->get('page');
        if ($page) {
            $params['page'] = $page;
        }

        $type = $request->get('type');
        if ($type) {
            $params['type'] = $type;
        }

        return $this->redirectToRoute('tbn_search_query', $params);
    }
}
