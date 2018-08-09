<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 29/12/2016
 * Time: 17:53.
 */

namespace TBN\MainBundle\Invalidator;

use FOS\HttpCacheBundle\Handler\TagHandler;
use Psr\Log\LoggerInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Entity\User;

class EventInvalidator
{
    private $tagHandler;

    private $logger;

    private $eventTags;

    private $userTags;

    public function __construct(TagHandler $tagHandler, LoggerInterface $logger)
    {
        $this->tagHandler = $tagHandler;
        $this->logger     = $logger;
        $this->eventTags  = [];
        $this->userTags   = [];
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

    public function invalidateEvents()
    {
        $tags = \array_filter(\array_unique(\array_merge(
            $this->eventTags,
            $this->userTags
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

    public function invalidateEvent(Agenda $event)
    {
        $this->addEvent($event);
        $this->invalidateEvents();
    }
}
