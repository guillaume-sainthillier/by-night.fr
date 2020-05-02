<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Repository\CityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CityManager
{
    private ?City $currentCity = null;

    private ?bool $cookieCity = null;

    private EntityManagerInterface $entityManager;

    private RequestStack $requestStack;
    /**
     * @var \App\Repository\CityRepository
     */
    private $cityRepository;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack, CityRepository $cityRepository)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->currentCity = null;
        $this->cookieCity = false;
        $this->cityRepository = $cityRepository;
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
        if (false === $this->cookieCity) {
            $this->computeCityFromCookie();
        }

        return $this->cookieCity;
    }

    private function computeCityFromCookie()
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest->cookies->has('app_city')) {
            $this->cookieCity = $this->cityRepository->findBySlug($currentRequest->cookies->get('app_city'));
        } else {
            $this->cookieCity = null;
        }
    }
}
