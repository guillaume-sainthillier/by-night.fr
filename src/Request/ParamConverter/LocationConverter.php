<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13.
 */

namespace App\Request\ParamConverter;

use App\App\CityManager;
use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use Doctrine\Common\Persistence\ObjectManager;
use InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocationConverter implements ParamConverterInterface
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(ObjectManager $em, CityManager $cityManager)
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

        if (is_object($locationSlug)) {
            return;
        }

        $location = new Location();
        $entity = $this
            ->em
            ->getRepository(City::class)
            ->findBySlug($locationSlug);

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
