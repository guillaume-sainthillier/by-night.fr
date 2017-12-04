<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13.
 */

namespace App\Request\ParamConverter;

use App\App\CityManager;
use App\Entity\City;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CityConverter implements ParamConverterInterface
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
        $this->em          = $em;
        $this->cityManager = $cityManager;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $city = $request->attributes->get('city');

        if (null === $city && !$configuration->isOptional()) {
            throw new \InvalidArgumentException('Route attribute is missing');
        } elseif (null === $city) {
            return;
        }

        if ($this->cityManager->getCurrentCity()) {
            $entity = $this->cityManager->getCurrentCity();
        } else {
            $entity = $this
                ->em
                ->getRepository('App:City')
                ->findBySlug($city);
        }

        if (!$entity) {
            throw new NotFoundHttpException(\sprintf(
                "La ville '%s' est introuvable",
                $city
            ));
        }

        $this->cityManager->setCurrentCity($entity);
        $request->attributes->set('_current_city', $entity->getSlug());
        $request->attributes->set($configuration->getName(), $entity);
    }

    public function supports(ParamConverter $configuration)
    {
        return City::class === $configuration->getClass();
    }
}
