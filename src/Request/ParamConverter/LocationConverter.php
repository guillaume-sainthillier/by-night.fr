<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Request\ParamConverter;

use App\App\CityManager;
use App\App\Location;
use App\Entity\City;
use App\Entity\Country;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocationConverter implements ValueResolverInterface
{
    public function __construct(private readonly CityManager $cityManager, private readonly CityRepository $cityRepository, private readonly CountryRepository $countryRepository)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $argumentType = $argument->getType();
        if (Location::class !== $argumentType) {
            return [];
        }

        $locationSlug = $request->attributes->get($argument->getName());
        $location = new Location();
        if ('unknown' === $locationSlug) {
            $noWhere = new Country();
            $noWhere->setName('Nowhere');
            $noWhere->setSlug($locationSlug);
            $location->setCountry($noWhere);

            return [$location];
        }

        if (!str_starts_with((string) $locationSlug, 'c--')) {
            $entity = $this->cityRepository->findOneBySlug($locationSlug);
        } else {
            $entity = $this->countryRepository->findOneBy(['slug' => $locationSlug]);
        }

        if ($entity instanceof City) {
            $location->setCity($entity);
            $this->cityManager->setCurrentCity($entity);
            $request->attributes->set('_current_city', $locationSlug);
        } elseif ($entity instanceof Country) {
            $location->setCountry($entity);
        } else {
            throw new NotFoundHttpException(\sprintf("La location '%s' est introuvable", $locationSlug));
        }

        return [$location];
    }
}
