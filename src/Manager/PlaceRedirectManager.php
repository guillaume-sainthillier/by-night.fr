<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\App\Location;
use App\Entity\Place;
use App\Exception\RedirectException;
use App\Repository\PlaceRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The venue of an agenda URL ("/toulouse/agenda/sortir-a/le-bikini"), throwing RedirectException when the URL is not
 * the venue's own: another location, the slug of a place merged into it, or none at all.
 */
final readonly class PlaceRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private PlaceRepository $placeRepository,
    ) {
    }

    /**
     * @throws RedirectException when the URL has to change: to the venue's own (301, its filters kept), or to the
     *                           location's page when no venue answers to it
     */
    public function getPlace(?string $placeSlug, Location $location): Place
    {
        $query = $this->requestStack->getCurrentRequest()?->query;

        // "/agenda/sortir-a" without a place is not a page of its own. The sitemap used to link it with the place as
        // "?slug=…", so that parameter still leads to the place's own URL, in the place's own city
        if (null === $placeSlug) {
            $legacySlug = $query?->getString('slug') ?? '';
            $place = '' !== $legacySlug ? $this->findPlace($legacySlug, $location) : null;

            throw new RedirectException(null === $place ? $this->getLocationUrl($location) : $this->router->generate('app_agenda_by_place', ['location' => $place->getLocationSlug(), 'placeSlug' => $place->getSlug()]));
        }

        $place = $this->findPlace($placeSlug, $location);
        if (null === $place) {
            throw new RedirectException($this->getLocationUrl($location), Response::HTTP_FOUND);
        }

        // Another location, or the slug of a place merged into this one
        if ($location->getSlug() !== $place->getLocationSlug() || $placeSlug !== $place->getSlug()) {
            throw new RedirectException($this->router->generate('app_agenda_by_place', [...$query?->all() ?? [], 'location' => $place->getLocationSlug(), 'placeSlug' => $place->getSlug()]));
        }

        return $place;
    }

    /**
     * Place slugs are not unique ("salle-des-fetes" names hundreds of places): the place in the city the URL names (or
     * without a city, in its country) comes first, then a place of that city merged into another one under that slug;
     * another one is only a fallback, which getPlace() redirects to its own URL. The location's city and country are
     * lazy proxies, hence their ids rather than the objects.
     */
    private function findPlace(string $slug, Location $location): ?Place
    {
        $city = $location->getCity();
        $country = $location->getCountry();
        $place = match (true) {
            null !== $city => $this->placeRepository->findOneBy(['slug' => $slug, 'city' => $city->getId()]),
            null !== $country => $this->placeRepository->findOneBy(['slug' => $slug, 'country' => $country->getId(), 'city' => null]),
            default => null,
        };

        return $place
            ?? $this->placeRepository->findOneByLegacySlug($slug, $location)
            ?? $this->placeRepository->findOneBy(['slug' => $slug]);
    }

    private function getLocationUrl(Location $location): string
    {
        return $this->router->generate('app_location_index', ['location' => $location->getSlug()]);
    }
}
