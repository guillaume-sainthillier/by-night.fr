<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 29/12/2016
 * Time: 17:53
 */

namespace TBN\MainBundle\Invalidator;


use FOS\HttpCacheBundle\Handler\TagHandler;
use Psr\Log\LoggerInterface;
use TBN\AgendaBundle\Entity\Agenda;

class EventInvalidator
{
    private $tagHandler;
    private $logger;
    private $eventTags;

    public function __construct(TagHandler $tagHandler, LoggerInterface $logger) {
        $this->tagHandler = $tagHandler;
        $this->logger = $logger;
        $this->eventTags = [];
    }

    public static function getEventDetailTag(Agenda $event) {
        return sprintf(
            'detail-event-%d',
            $event->getId()
        );
    }

    public function addEvent(Agenda $event) {
        $this->tags[] = self::getEventDetailTag($event);
    }

    public function invalidateEvents() {
        $tags = array_filter(array_unique($this->eventTags));

        if(! count($tags)) {
            return;
        }

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch(\Exception $e) {
            $this->logger->critical($e);
        }

        unset($this->eventTags);
        $this->eventTags = [];
    }

    public function invalidateEvent(Agenda $event) {
        $this->addEvent($event);
        $this->invalidateEvents();
    }
}