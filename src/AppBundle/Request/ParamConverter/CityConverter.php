<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13.
 */

namespace AppBundle\Request\ParamConverter;

use AppBundle\App\CityManager;
use AppBundle\Entity\City;
use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CityConverter implements ParamConverterInterface
{
    /**
     * @var AbstractManagerRegistry
     */
    private $registry;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(AbstractManagerRegistry $registry, CityManager $cityManager)
    {
        $this->registry    = $registry;
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
                ->registry
                ->getManager()
                ->getRepository('AppBundle:City')
                ->findBySlug($city);
        }

        if (!$entity) {
            throw new NotFoundHttpException(sprintf(
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
        return $configuration->getClass() === City::class;
    }
}
