<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 19:13
 */

namespace AppBundle\Request\ParamConverter;


use AppBundle\Entity\City;
use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CityConverter implements ParamConverterInterface
{
    private $registry;

    public function __construct(AbstractManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function apply(Request $request, ParamConverter $configuration)
    {
        $city = $request->attributes->get('city');

        if (null === $city && ! $configuration->isOptional()) {
            throw new \InvalidArgumentException('Route attribute is missing');
        }elseif(null === $city) {
            return;
        }

        $entity = $this
            ->registry
            ->getManager()
            ->getRepository("AppBundle:City")
            ->findBySlug($city);

        if(! $entity) {
            throw new NotFoundHttpException(sprintf(
                "La ville '%s' est introuvable",
                $city
            ));
        }

        $request->attributes->set($configuration->getName(), $entity);
    }

    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() === City::class;
    }
}
