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

    public function invalidateEvent(Agenda $event) {
        $idEvent = $event->getId();

        $tags = [
            'detail-event-' . $idEvent,
        ];

        $this->tagHandler->invalidateTags($tags);
    }
}