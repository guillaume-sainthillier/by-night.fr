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
use App\Entity\City;
use App\Entity\Country;
use Symfony\Component\Routing\Annotation\Route;

class CommonController extends TBNController
{
    const LIFE_TIME_CACHE = 86400; // 3600*24

    /**
     * @Route("/_private/header/{id}", name="app_private_header", requirements={"id": "\d+"})
     * @ReverseProxy(expires="+1 day")
     */
    public function header(CityManager $cityManager, $id = null)
    {
        $city = null;
        if ($id) {
            $city = $this->getDoctrine()->getRepository(City::class)->find($id);
        }

        $city = $city ?: $cityManager->getCity();

        return $this->render('fragments/menu.html.twig', [
            'city' => $city,
        ]);
    }

    public function footer(Country $country = null)
    {
        $params = [];
        $repo = $this->getDoctrine()->getRepository(City::class);
        $params['cities'] = $repo->findRandomNames($country);

        return $this->render('fragments/footer.html.twig', $params);
    }
}
