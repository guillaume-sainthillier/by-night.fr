<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Picture\OriginUrlResolver;
use Silarhi\PicassoBundle\Exception\ImageNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends an unservable Picasso image URL to the public URL of its original file.
 *
 * A Picasso URL only validates together with its signature and "_metadata" query
 * parameters. Consumers that drop the query string — crawlers, feed readers, link
 * shorteners — end up requesting a path we cannot serve. Redirecting to the origin
 * keeps the image resolving and the link worth something, instead of a 404.
 */
final readonly class PicassoOriginRedirectSubscriber implements EventSubscriberInterface
{
    /**
     * The redirect is permanent, but the target embeds the current public URL scheme,
     * so it is cached for a month rather than pinned forever.
     */
    private const int REDIRECT_MAX_AGE = 2592000;

    public function __construct(private OriginUrlResolver $originUrlResolver)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof NotFoundHttpException
            || !$throwable->getPrevious() instanceof ImageNotFoundException) {
            return;
        }

        $request = $event->getRequest();

        if ('picasso_image' !== $request->attributes->getString('_route')) {
            return;
        }

        $url = $this->originUrlResolver->resolve(
            $request->attributes->getString('loader'),
            $request->attributes->getString('path'),
        );

        // No storage holds the file: let the 404 stand rather than redirect into one.
        if (null === $url) {
            return;
        }

        $response = new RedirectResponse($url, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();
        $response->setMaxAge(self::REDIRECT_MAX_AGE);

        $event->setResponse($response);
    }
}
