<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/06/2017
 * Time: 15:20.
 */

namespace AppBundle\App;

use AppBundle\Entity\City;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CityManager
{
    /**
     * @var City|null
     */
    private $currentCity;

    /**
     * @var City|null
     */
    private $cookieCity;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack  = $requestStack;
        $this->currentCity   = null;
        $this->cookieCity    = false;
    }

    /**
     * @param City $city
     *
     * @return $this
     */
    public function setCurrentCity(City $city)
    {
        $this->currentCity = $city;

        return $this;
    }

    private function computeCityFromCookie()
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest->cookies->has('app_city')) {
            $this->cookieCity = $this->entityManager->getRepository('AppBundle:City')->findBySlug($currentRequest->cookies->get('app_city'));
        } else {
            $this->cookieCity = null;
        }
    }

    /**
     * @return City|null
     */
    public function getCurrentCity()
    {
        return $this->currentCity;
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

    /**
     * @return City|null
     */
    public function getCity()
    {
        return $this->getCurrentCity() ?: $this->getCookieCity();
    }
}
