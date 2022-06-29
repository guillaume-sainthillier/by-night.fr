<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Api;

use App\Annotation\ReverseProxy;
use App\Controller\AbstractController;
use App\Entity\City;
use App\Invalidator\TagsInvalidator;
use App\SearchRepository\CityElasticaRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\HttpCache\ResponseTagger;
use FOS\HttpCacheBundle\Configuration\Tag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api')]
class CityController extends AbstractController
{
    /**
     * @var int
     */
    public const MAX_RESULTS = 7;

    /**
     * @ReverseProxy(expires="1 year")
     * @Tag("autocomplete-city")
     */
    #[Route(path: '/villes', name: 'app_api_city', methods: ['GET'])]
    public function city(ResponseTagger $responseTagger, Request $request, RepositoryManagerInterface $repositoryManager): Response
    {
        $term = trim($request->get('q'));
        if ('' === $term) {
            $results = [];
        } else {
            /** @var CityElasticaRepository $repo */
            $repo = $repositoryManager->getRepository(City::class);
            $results = $repo->findWithSearch($term);
            $this->updatePaginator($results, 1, self::MAX_RESULTS);
        }

        $jsonResults = [];
        /** @var City $result */
        foreach ($results as $result) {
            $responseTagger->addTags([TagsInvalidator::getCityTag($result)]);
            $jsonResults[] = [
                'slug' => $result->getSlug(),
                'name' => $result->getFullName(),
            ];
        }

        return new JsonResponse($jsonResults);
    }
}
