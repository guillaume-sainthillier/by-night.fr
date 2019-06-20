<?php


namespace App\Invalidator;

use App\Entity\Calendrier;
use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use Exception;
use FOS\HttpCacheBundle\CacheManager;
use Psr\Log\LoggerInterface;

class TagsInvalidator
{
    /**
     * @var CacheManager
     */
    private $tagHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var array
     */
    private $tags;

    public function __construct(CacheManager $tagHandler, LoggerInterface $logger, $debug)
    {
        $this->tagHandler = $tagHandler;
        $this->logger = $logger;
        $this->debug = $debug;
        $this->tags = [];
    }

    public static function getEventTag(Event $event)
    {
        return \sprintf('event-%d', $event->getId());
    }

    public static function getTendanceTag(Event $event)
    {
        return \sprintf('tendances-%d', $event->getId());
    }

    public static function getPlaceTag(Place $place)
    {
        return \sprintf('place-%d', $place->getId());
    }

    public static function getUserTag(User $user)
    {
        return \sprintf('user-%d', $user->getId());
    }

    public static function getMenuTag()
    {
        return 'menu';
    }

    public function addCity(City $city)
    {
        $this->tags[] = 'autocomplete-city';
    }

    public function addUser(User $user)
    {
        $this->tags[] = self::getUserTag($user);
    }

    public function addCalendrier(Calendrier $calendrier)
    {
        $this->tags[] = self::getTendanceTag($calendrier->getEvent());
    }

    public function addEvent(Event $event)
    {
        if ($event->getId()) {
            $this->tags[] = self::getEventTag($event);
        }

        if ($event->getPlace()) {
            $this->addPlace($event->getPlace());
        }
    }

    public function addPlace(Place $place)
    {
        if ($place->getId()) {
            $this->tags[] = self::getPlaceTag($place);
        }
    }

    public function flush()
    {
        if ($this->debug) {
            unset($this->tags); //Call GC
            $this->tags = [];

            return;
        }

        $tags = \array_filter(\array_unique($this->tags));

        if (!\count($tags)) {
            return;
        }

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch (Exception $e) {
            $this->logger->critical($e);
        }

        unset($this->tags); //Call GC
        $this->tags = [];
    }
}
