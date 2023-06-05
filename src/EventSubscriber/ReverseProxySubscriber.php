<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Annotation\ReverseProxy;
use DateTime;
use DateTimeZone;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ReverseProxySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly bool $enableHttpCache)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
        ];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event)
    {
        $request = $event->getRequest();

        if (!\is_array($attributes = $request->attributes->get('_reverse_proxy') ?? $event->getAttributes()[ReverseProxy::class] ?? null)) {
            return;
        }

        $request->attributes->set('_reverse_proxy', $attributes);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->enableHttpCache) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethodCacheable() || !$request->attributes->has('_reverse_proxy')) {
            return;
        }

        /** @var ReverseProxy[] $reverseProxyConfigurations */
        $reverseProxyConfigurations = $request->attributes->get('_reverse_proxy');

        foreach ($reverseProxyConfigurations as $reverseProxyConfiguration) {
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
            $response->headers->set('X-Reverse-Proxy-TTL', $ttl);
        }
    }
}
