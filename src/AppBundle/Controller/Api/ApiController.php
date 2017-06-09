<?php

namespace AppBundle\Controller\Api;

use AppBundle\Entity\City;
use AppBundle\SearchRepository\CityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Configuration\BrowserCache;

/**
 * @Route("/api")
 */
class ApiController extends Controller
{
    const MAX_RESULTS = 7;

    /**
     * @Route("/villes", name="app_api_city")
     * @Cache(expires="+1 week", maxage="604800", smaxage="604800", public=true)
     * @BrowserCache(false)
     */
    public function cityAutocompleteAction(Request $request)
    {
        $term = trim($request->get('q'));
        if (!$term) {
            $results = [];
        } else {
            $paginator = $this->get('knp_paginator');

            /**
             * @var CityRepository
             */
            $repo    = $this->get('fos_elastica.manager')->getRepository('AppBundle:City');
            $results = $repo->findWithSearch($term);
            $results = $paginator->paginate($results, 1, self::MAX_RESULTS);
        }

        $jsonResults = [];
        foreach ($results as $result) {
            /*
             * @var City
             */
            if (!$result->getParent()) {
                continue;
            }
            $jsonResults[] = [
                'slug' => $result->getSlug(),
                'name' => sprintf('%s (%s, %s)', $result->getName(), $result->getParent()->getName(), $result->getCountry()->getName()),
            ];
        }

        return new JsonResponse($jsonResults);
    }
}
