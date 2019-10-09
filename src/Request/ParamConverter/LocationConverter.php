<?php

namespace App\Request\ParamConverter;

use App\App\CityManager;
use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocationConverter implements ParamConverterInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(EntityManagerInterface $em, CityManager $cityManager)
    {
        $this->em = $em;
        $this->cityManager = $cityManager;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $locationSlug = $request->attributes->get('location');

        if (null === $locationSlug && !$configuration->isOptional()) {
            throw new InvalidArgumentException('Route attribute is missing');
        } elseif (null === $locationSlug) {
            return;
        }

        if (\is_object($locationSlug)) {
            return;
        }

        $location = new Location();
        $entity = null;
        if(strpos('c--', $locationSlug) !== 0) {
            $entity = $this
                ->em
                ->getRepository(City::class)
                ->findBySlug($locationSlug);
        }

        if($locationSlug === 'unknown') {
            $noWhere = new Country();
            $noWhere->setName('Nowhere');
            $noWhere->setSlug($locationSlug);
            $location->setCountry($noWhere);
            $request->attributes->set($configuration->getName(), $location);
            return;
        }

        if ($entity) {
            $location->setCity($entity);
            $this->cityManager->setCurrentCity($entity);
            $request->attributes->set('_current_city', $locationSlug);
        } else {
            $entity = $this
                ->em
                ->getRepository(Country::class)
                ->findOneBy(['slug' => $locationSlug]);
            $location->setCountry($entity);
        }

        if (!$entity) {
            throw new NotFoundHttpException(\sprintf(
                "La location '%s' est introuvable",
                $locationSlug
            ));
        }

        $request->attributes->set($configuration->getName(), $location);
    }

    public function supports(ParamConverter $configuration)
    {
        return Location::class === $configuration->getClass();
    }
}
