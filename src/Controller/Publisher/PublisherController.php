<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Publisher;

use App\Controller\AbstractController as BaseController;
use App\Entity\Event;
use App\Entity\Publisher;
use App\Repository\EventRepository;
use App\Repository\PublisherLegacySlugRepository;
use App\Repository\PublisherRepository;
use App\Stats\PublisherProfileProvider;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The page of a publisher: who it is, its links, what its events add up to and the distinctions they earn it, its
 * events online, and for the members who manage it, what is left to complete it.
 */
#[Route(path: '/organisateurs')]
final class PublisherController extends BaseController
{
    public const int EVENTS_PER_PAGE = 6;

    #[Route(path: '/{slug<%patterns.slug%>}', name: 'app_publisher_index', methods: ['GET'])]
    public function index(string $slug, PublisherRepository $publisherRepository, PublisherLegacySlugRepository $legacySlugRepository, EventRepository $eventRepository, PublisherProfileProvider $publisherProfileProvider): Response
    {
        $publisher = $publisherRepository->findOneBy(['slug' => $slug]);
        if (null === $publisher) {
            // A deleted publisher's page leads to the one that took its events
            $heir = $legacySlugRepository->findPublisherBySlug($slug) ?? throw $this->createNotFoundException(\sprintf('No publisher "%s".', $slug));

            return $this->redirectToRoute('app_publisher_index', ['slug' => $heir->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->render('publisher/index.html.twig', [
            'publisher' => $publisher,
            'profile' => $publisherProfileProvider->get($publisher),
            'nextEvents' => $this->paginateEvents($eventRepository, $publisher, true, 1),
            'previousEvents' => $this->paginateEvents($eventRepository, $publisher, false, 1),
        ]);
    }

    #[Route(path: '/{slug<%patterns.slug%>}/a-venir/{page<%patterns.page%>}', name: 'app_publisher_events_next', methods: ['GET'], defaults: ['upcoming' => true])]
    #[Route(path: '/{slug<%patterns.slug%>}/passes/{page<%patterns.page%>}', name: 'app_publisher_events_previous', methods: ['GET'], defaults: ['upcoming' => false])]
    public function eventsList(#[MapEntity(mapping: ['slug' => 'slug'])] Publisher $publisher, EventRepository $eventRepository, bool $upcoming, int $page): Response
    {
        return $this->render('publisher/events_list.html.twig', [
            'publisher' => $publisher,
            'events' => $this->paginateEvents($eventRepository, $publisher, $upcoming, $page),
            'isNext' => $upcoming,
        ]);
    }

    /**
     * @return PagerfantaInterface<Event>
     */
    private function paginateEvents(EventRepository $eventRepository, Publisher $publisher, bool $upcoming, int $page): PagerfantaInterface
    {
        return $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findPublisherEventsQueryBuilder($publisher, $upcoming),
            $eventRepository,
            $page,
            self::EVENTS_PER_PAGE,
            ['view' => 'events:user:list'],
            fetchJoinCollection: false,
        );
    }
}
