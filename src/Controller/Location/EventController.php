<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\CityManager;
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Controller\Comment\CommentController;
use App\Entity\Comment;
use App\Entity\Event;
use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Form\Type\CommentType;
use App\Manager\WidgetsManager;
use App\Picture\EventProfilePicture;
use App\Repository\CommentRepository;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EventController extends BaseController
{
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_event_details', methods: ['GET'])]
    #[Route(path: '/soiree/{slug<%patterns.slug%>}', name: 'app_event_details_old', methods: ['GET'])]
    public function index(Location $location, EventDispatcherInterface $eventDispatcher, CityManager $cityManager, EventProfilePicture $eventProfilePicture, CommentRepository $commentRepository, WidgetsManager $widgetsManager, string $slug, ?int $id = null): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_event_details');
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }

        $event = $eventCheck->getEvent();

        // Build Page object for social sharing
        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $eventProfile = $eventProfilePicture->getOriginalPicture($event);
        $page = new Page([
            'url' => $link,
            'title' => $event->getName(),
            'text' => $event->getDescription(),
            'image' => $eventProfile,
        ]);

        // Widget data (first page only)
        $user = $this->getUser();
        \assert($user instanceof \App\Entity\User || null === $user);
        $trendsData = $widgetsManager->getTrendsData($event, $user, $page);
        $nextEventsData = $widgetsManager->getNextEventsData($event, $location);
        $similarEventsData = $widgetsManager->getSimilarEventsData($event, $location);

        // Comments widget data (first page)
        $comments = $this->createQueryBuilderPaginator(
            $commentRepository->findAllByEventQueryBuilder($event),
            1,
            CommentController::COMMENTS_PER_PAGE
        );

        $commentForm = null;
        if ($this->isGranted('ROLE_USER')) {
            $comment = new Comment();
            $commentForm = $this
                ->createForm(CommentType::class, $comment, [
                    'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
                ])
                ->createView();
        }

        $renderData = [
            'location' => $location,
            'event' => $event,
            'headerCity' => $location->getCity() ?? $cityManager->getCity(),
            // Trends widget
            'trendsData' => $trendsData,
            // Similar events widget
            'similarEventsData' => $similarEventsData,
            // Comments widget
            'comments' => $comments,
            'commentForm' => $commentForm,
            'nextEventsData' => $nextEventsData,
        ];

        return $this->render('location/event/index.html.twig', $renderData);
    }

    #[Cache(expires: '+12 hours', smaxage: 43200)]
    public function share(Event $event, EventProfilePicture $eventProfilePicture): Response
    {
        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $eventProfile = $eventProfilePicture->getOriginalPicture($event);
        $page = new Page([
            'url' => $link,
            'title' => $event->getName(),
            'text' => $event->getDescription(),
            'image' => $eventProfile,
        ]);

        return $this->render('location/hinclude/shares.html.twig', [
            'shares' => [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter,
            ],
        ]);
    }
}
