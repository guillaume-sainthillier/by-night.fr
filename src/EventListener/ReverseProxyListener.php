<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Annotation\ReverseProxy;
use DateTime;
use DateTimeZone;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ReverseProxyListener implements EventSubscriberInterface
{
    private bool $debug;

    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if ($this->debug) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethodCacheable() || !$request->attributes->has('_reverse_proxy')) {
            return;
        }

        /** @var ReverseProxy $reverseProxyConfiguration */
        $reverseProxyConfiguration = $request->attributes->get('_reverse_proxy');

        if (null === $reverseProxyConfiguration->getTtl() && null === $reverseProxyConfiguration->getExpires()) {
            return;
        }

        if (null !== $reverseProxyConfiguration->getTtl()) {
            $ttl = $reverseProxyConfiguration->getTtl();
        } else {
            $date = DateTime::createFromFormat('U', strtotime($reverseProxyConfiguration->getExpires()), new DateTimeZone('UTC'));
            $now = DateTime::createFromFormat('U', strtotime('now'), new DateTimeZone('UTC'));

            $ttl = max($date->format('U') - $now->format('U'), 0);
        }

        $response = $event->getResponse();
        $response->headers->set('X-Reverse-Proxy-TTL', $ttl, false);
    }
}
