<?php

namespace App\Controller\Api;

use App\Annotation\ReverseProxy;
use App\Entity\City;
use App\SearchRepository\CityRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\HttpCacheBundle\Configuration\Tag;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api")
 */
class ApiController extends AbstractController
{
    const MAX_RESULTS = 7;

    /**
     * @Route("/villes", name="app_api_city")
     * @ReverseProxy(expires="1 year")
     * @Tag("autocomplete-city")
     *
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @param RepositoryManagerInterface $repositoryManager
     *
     * @return JsonResponse
     */
    public function cityAutocompleteAction(Request $request, PaginatorInterface $paginator, RepositoryManagerInterface $repositoryManager)
    {
        $term = \trim($request->get('q'));
        if (!$term) {
            $results = [];
        } else {
            /** @var CityRepository $repo */
            $repo = $repositoryManager->getRepository(City::class);
            $results = $repo->findWithSearch($term);
            $results = $paginator->paginate($results, 1, self::MAX_RESULTS);
        }

        $jsonResults = [];
        foreach ($results as $result) {
            /** @var City $result */
            $jsonResults[] = [
                'slug' => $result->getSlug(),
                'name' => $result->getFullName(),
            ];
        }

        return new JsonResponse($jsonResults);
    }
}
