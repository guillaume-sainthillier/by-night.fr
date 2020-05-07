<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Controller\TBNController;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86_400;

    /**
     * @Route("/_private/header/{id}", name="app_private_header", requirements={"id": "\d+"})
     * @ReverseProxy(expires="+1 day")
     */
    public function header(CityManager $cityManager, CityRepository $cityRepository, ?int $id = null)
    {
        $city = null;
        if ($id) {
            $city = $cityRepository->find($id);
        }

        $city = $city ?: $cityManager->getCity();

        return $this->render('fragments/menu.html.twig', [
            'city' => $city,
        ]);
    }

    public function footer(CityRepository $cityRepository, Country $country = null)
    {
        $params = [];
        $repo = $cityRepository;
        $params['cities'] = $repo->findRandomNames($country);

        return $this->render('fragments/footer.html.twig', $params);
    }
}
