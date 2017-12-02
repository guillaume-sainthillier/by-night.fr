<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 29/12/2016
 * Time: 17:53.
 */

namespace AppBundle\Invalidator;

use AppBundle\Entity\City;
use FOS\HttpCacheBundle\CacheManager;
use FOS\HttpCacheBundle\Handler\TagHandler;
use Psr\Log\LoggerInterface;
use AppBundle\Entity\Agenda;
use AppBundle\Entity\User;

class EventInvalidator
{
    /**
     * @var TagHandler
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
    private $eventTags;

    /**
     * @var array
     */
    private $userTags;

    /**
     * @var array
     */
    private $cityTags;

    public function __construct(CacheManager $tagHandler, LoggerInterface $logger, $debug)
    {
        $this->tagHandler = $tagHandler;
        $this->logger     = $logger;
        $this->debug      = $debug;
        $this->eventTags  = [];
        $this->userTags   = [];
        $this->cityTags   = [];
    }

    public static function getEventDetailTag(Agenda $event)
    {
        return \sprintf(
            'detail-event-%d',
            $event->getId()
        );
    }

    public static function getUserDetailTag(User $user)
    {
        return \sprintf(
            'detail-user-%d',
            $user->getId()
        );
    }

    public static function getUserMenuTag(User $user)
    {
        return \sprintf(
            'menu-%d',
            $user->getId()
        );
    }

    public function addCity(City $city)
    {
        $this->cityTags[] = 'autocomplete_city';
    }

    public function addUser(User $user)
    {
        $this->userTags[] = self::getUserMenuTag($user);
        $this->userTags[] = self::getUserDetailTag($user);
    }

    public function addEvent(Agenda $event)
    {
        $this->eventTags[] = self::getEventDetailTag($event);

        if ($event->getPlace() && $event->getPlace()->getId()) {
            $this->eventTags[] = \sprintf('detail-place-%d', $event->getPlace()->getId());
        }
    }

    public function invalidateObjects()
    {
        if ($this->debug) {
            return;
        }

        $tags = \array_filter(\array_unique(\array_merge(
            $this->eventTags,
            $this->userTags,
            $this->cityTags
        )));

        if (!\count($tags)) {
            return;
        }

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }

        unset($this->eventTags);
        $this->eventTags = [];
    }
}
