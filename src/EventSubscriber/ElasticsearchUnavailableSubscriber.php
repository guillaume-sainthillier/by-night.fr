<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Exception\ElasticsearchUnavailableException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastic\Transport\Exception\NoNodeAvailableException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * A restart of Elasticsearch fails every agenda, search and autocomplete for a minute or two: those pages answer 503
 * Service Unavailable, to be retried in a while, rather than 500.
 */
final class ElasticsearchUnavailableSubscriber implements EventSubscriberInterface
{
    /** Seconds: an Elasticsearch restart, shards included. */
    private const int RETRY_AFTER = 120;

    public static function getSubscribedEvents(): array
    {
        // Before ErrorListener::logKernelException (0), which logs the exception it is handed
        return [KernelEvents::EXCEPTION => ['onKernelException', 8]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $outage = self::find($event->getThrowable());
        if (null !== $outage) {
            $event->setThrowable(new ElasticsearchUnavailableException(self::RETRY_AFTER, $outage));
        }
    }

    /**
     * The failure of Elasticsearch, also when a template that read a lazy paginator wrapped it. The node is down (a
     * refused connection ends there too, once the transport has marked the only node dead), or it is up without the
     * shards of the index yet.
     */
    private static function find(?Throwable $throwable): ?Throwable
    {
        for (; null !== $throwable; $throwable = $throwable->getPrevious()) {
            if ($throwable instanceof NoNodeAvailableException
                || ($throwable instanceof ServerResponseException && Response::HTTP_SERVICE_UNAVAILABLE === $throwable->getCode())) {
                return $throwable;
            }
        }

        return null;
    }
}
