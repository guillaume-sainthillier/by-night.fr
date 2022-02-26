<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class CityManager
{
    private ?City $currentCity = null;

    private ?City $cookieCity = null;
    private bool $initCooky = false;

    public function __construct(private RequestStack $requestStack, private CityRepository $cityRepository)
    {
    }

    /**
     * @return City|null
     */
    public function getCity()
    {
        return $this->getCurrentCity() ?: $this->getCookieCity();
    }

    /**
     * @return City|null
     */
    public function getCurrentCity()
    {
        return $this->currentCity;
    }

    /**
     * @return $this
     */
    public function setCurrentCity(City $city)
    {
        $this->currentCity = $city;

        return $this;
    }

    /**
     * @return City|null
     */
    public function getCookieCity()
    {
        if (!$this->initCooky) {
            $this->computeCityFromCookie();
            $this->initCooky = true;
        }

        return $this->cookieCity;
    }

    private function computeCityFromCookie(): void
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest->cookies->has('app_city')) {
            $this->cookieCity = $this->cityRepository->findOneBySlug($currentRequest->cookies->get('app_city'));
        } else {
            $this->cookieCity = null;
        }
    }
}
