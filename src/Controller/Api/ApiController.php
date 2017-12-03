<?php

namespace AppBundle\Controller\Api;

use AppBundle\SearchRepository\CityRepository;
use FOS\ElasticaBundle\Doctrine\RepositoryManager;
use FOS\HttpCacheBundle\Configuration\Tag;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Annotation\BrowserCache;

/**
 * @Route("/api")
 */
class ApiController extends Controller
{
    const MAX_RESULTS = 7;

    /**
     * @Route("/villes", name="app_api_city")
     * @Cache(expires="+1 month", maxage="2592000", smaxage="2592000", public=true)
     * @BrowserCache(false)
     * @Tag("autocomplete_city")
     */
    public function cityAutocompleteAction(Request $request)
    {
        $term = \trim($request->get('q'));
        if (!$term) {
            $results = [];
        } else {
            $paginator = $this->get(PaginatorInterface::class);

            /**
             * @var CityRepository
             */
            $repo    = $this->get(RepositoryManager::class)->getRepository('AppBundle:City');
            $results = $repo->findWithSearch($term);
            $results = $paginator->paginate($results, 1, self::MAX_RESULTS);
        }

        $jsonResults = [];
        foreach ($results as $result) {
            /*
             * @var City
             */
            $jsonResults[] = [
                'slug' => $result->getSlug(),
                'name' => $result->getFullName(),
            ];
        }

        return new JsonResponse($jsonResults);
    }
}
