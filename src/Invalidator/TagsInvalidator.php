<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Invalidator;

use App\App\Location;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\UserEvent;
use Exception;
use FOS\HttpCacheBundle\CacheManager;
use Psr\Log\LoggerInterface;

class TagsInvalidator
{
    private array $tags = [];

    public function __construct(private CacheManager $tagHandler, private LoggerInterface $logger, private bool $debug)
    {
    }

    public static function getHeaderTag(): string
    {
        return 'header';
    }

    public function addCity(City $city): void
    {
        $this->tags[] = 'autocomplete-city';
        $this->tags[] = self::getCityTag($city);
    }

    public static function getCityTag(City $city): string
    {
        return sprintf('city-%d', $city->getId());
    }

    public function addUser(User $user): void
    {
        $this->tags[] = self::getUserTag($user);
    }

    public static function getUserTag(User $user): string
    {
        return sprintf('user-%d', $user->getId());
    }

    public function addUserEvent(UserEvent $userEvent): void
    {
        $this->tags[] = self::getTrendTag($userEvent->getEvent());
    }

    public static function getTrendTag(Event $event): string
    {
        return sprintf('tendances-%d', $event->getId());
    }

    public function addEvent(Event $event): void
    {
        if ($event->getId()) {
            $this->tags[] = self::getEventTag($event);
        }

        if (null !== $event->getPlace()) {
            $this->addPlace($event->getPlace());
        }
    }

    public static function getEventTag(Event $event): string
    {
        return sprintf('event-%d', $event->getId());
    }

    public static function getLocationTag(Location $location): string
    {
        return sprintf('location-%s', $location->getId());
    }

    public function addPlace(Place $place): void
    {
        if ($place->getId()) {
            $this->tags[] = self::getPlaceTag($place);
        }

        $this->tags[] = self::getLocationTag($place->getLocation());
    }

    public static function getPlaceTag(Place $place): string
    {
        return sprintf('place-%d', $place->getId());
    }

    public function flush(): void
    {
        if ($this->debug) {
            unset($this->tags); // Call GC
            $this->tags = [];

            return;
        }

        $tags = array_filter(array_unique($this->tags));

        if (0 === \count($tags)) {
            return;
        }

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
            ]);
        }

        unset($this->tags); // Call GC
        $this->tags = [];
    }
}
