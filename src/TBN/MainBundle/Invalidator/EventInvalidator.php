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

    public function __construct(TagHandler $tagHandler, LoggerInterface $logger) {
        $this->tagHandler = $tagHandler;
        $this->logger = $logger;
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

        try {
            $this->tagHandler->invalidateTags($tags);
        } catch(\Exception $e) {
            $this->logger->critical($e);
        }
    }
}