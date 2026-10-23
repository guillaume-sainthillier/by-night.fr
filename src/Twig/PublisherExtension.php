<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\App\PublisherContext;
use App\Entity\Publisher;
use App\Entity\User;
use App\EventSubscriber\PublisherContextSubscriber;
use App\Repository\PublisherRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The publisher switcher of the personal space: the publisher the page is about, the others the member runs, and where
 * each of them leads.
 */
final readonly class PublisherExtension
{
    public function __construct(
        private PublisherContext $publisherContext,
        private PublisherRepository $publisherRepository,
        private Security $security,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The publisher whose organizer page this is, if any.
     */
    #[AsTwigFunction(name: 'current_publisher')]
    public function currentPublisher(): ?Publisher
    {
        return $this->publisherContext->find();
    }

    /**
     * @return list<Publisher> the publishers the member runs, by name
     */
    #[AsTwigFunction(name: 'managed_publishers')]
    public function managedPublishers(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->publisherRepository->findByOwner($user) : [];
    }

    /**
     * The same page for another publisher (its events, its imports…); its list of events from a page of one of the
     * current publisher's events or jobs, which the other one does not have.
     */
    #[AsTwigFunction(name: 'publisher_switch_url')]
    public function publisherSwitchUrl(Publisher $publisher): string
    {
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->getString('_route');
        /** @var array<string, mixed> $parameters */
        $parameters = $request?->attributes->get('_route_params') ?? [];
        $parameter = PublisherContextSubscriber::ROUTE_PARAMETER;
        if (null === $route || '' === $route || [$parameter] !== array_keys($parameters)) {
            return $this->urlGenerator->generate('app_event_list', [$parameter => $publisher->getSlug()]);
        }

        return $this->urlGenerator->generate($route, [$parameter => $publisher->getSlug()]);
    }
}
