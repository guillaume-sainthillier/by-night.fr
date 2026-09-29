<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Application context service that holds the current location.
 * Acts as a centralized holder for location state that can be accessed globally.
 *
 * The Location object may contain lazy-loaded City/Country entities (PHP 8.4 LazyObject)
 * that are only loaded from the database when their properties are accessed.
 */
final class AppContext implements ResetInterface
{
    private ?Location $location = null;

    public function __construct(private readonly Security $security)
    {
    }

    public function reset(): void
    {
        $this->location = null;
    }

    /**
     * Get the current location: the URL's, else the city the member chose on their profile.
     *
     * The member is only looked up when a page reads the location (its header does), not on every
     * request: an API call or an image would otherwise open the session of a logged-in visitor.
     */
    public function getLocation(): ?Location
    {
        return $this->location ??= $this->getMemberLocation();
    }

    /**
     * Set the current location.
     */
    public function setLocation(Location $location): void
    {
        $this->location = $location;
    }

    /**
     * Get the city from the current location.
     * Note: This may trigger lazy loading if the city is a ghost object.
     */
    public function getCity(): ?City
    {
        return $this->getLocation()?->getCity();
    }

    /**
     * Get the country from the current location.
     * Note: This may trigger lazy loading if the country is a ghost object.
     */
    public function getCountry(): ?Country
    {
        return $this->getLocation()?->getCountry();
    }

    /**
     * Get the slug from the current location.
     * Note: This may trigger lazy loading.
     */
    public function getSlug(): ?string
    {
        return $this->getLocation()?->getSlug();
    }

    /**
     * Check if the current location is a city.
     * This does NOT trigger lazy loading as it only checks if the city property is set.
     */
    public function isCity(): bool
    {
        return $this->getLocation()?->isCity() ?? false;
    }

    /**
     * Check if the current location is a country.
     * This does NOT trigger lazy loading as it only checks if the country property is set.
     */
    public function isCountry(): bool
    {
        return $this->getLocation()?->isCountry() ?? false;
    }

    private function getMemberLocation(): ?Location
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || null === $user->getCity()) {
            return null;
        }

        return new Location()->setCity($user->getCity());
    }
}
