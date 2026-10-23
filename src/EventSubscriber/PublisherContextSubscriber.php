<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\App\PublisherContext;
use App\Repository\PublisherRepository;
use App\Security\Voter\PublisherVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Resolves the {publisher} of the organizer pages (/espace-perso/organisateurs/{publisher}/…): unknown, 404; not
 * managed by the member, 403. It then sticks to the URLs those pages generate, as _locale would: path('app_event_list')
 * stays on the same publisher, and a link to another one names it.
 *
 * After the firewall (priority 8), which has authenticated the member by then.
 */
final readonly class PublisherContextSubscriber implements EventSubscriberInterface
{
    public const string ROUTE_PARAMETER = 'publisher';

    public function __construct(
        private PublisherRepository $publisherRepository,
        private PublisherContext $publisherContext,
        private RouterInterface $router,
        private Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // The router outlives the request in a worker: the previous request's publisher must not stick to this one
        $context = $this->router->getContext();
        $parameters = $context->getParameters();
        unset($parameters[self::ROUTE_PARAMETER]);
        $context->setParameters($parameters);

        $slug = $event->getRequest()->attributes->get('_route_params')[self::ROUTE_PARAMETER] ?? null;
        if (!\is_string($slug)) {
            return;
        }

        $publisher = $this->publisherRepository->findOneBy(['slug' => $slug])
            ?? throw new NotFoundHttpException(\sprintf('No publisher "%s".', $slug));
        if (!$this->security->isGranted(PublisherVoter::EDIT, $publisher)) {
            throw new AccessDeniedException('The member does not manage this publisher.');
        }

        $this->publisherContext->set($publisher);
        $context->setParameter(self::ROUTE_PARAMETER, $slug);
    }
}
