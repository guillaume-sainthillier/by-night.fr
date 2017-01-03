<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 29/12/2016
 * Time: 17:53
 */

namespace TBN\MainBundle\Invalidator;


use FOS\HttpCacheBundle\Handler\TagHandler;
use TBN\AgendaBundle\Entity\Agenda;

class EventInvalidator
{
    private $tagHandler;

    public function __construct(TagHandler $tagHandler) {
        $this->tagHandler = $tagHandler;
    }

    public static function getEventDetailTag(Agenda $event) {
        return sprintf(
            'detail-event-%d',
            $event->getId()
        );
    }

    public function invalidateEvent(Agenda $event) {
        $tags = [
            self::getEventDetailTag($event)
        ];

        $this->tagHandler->invalidateTags($tags);
    }
}