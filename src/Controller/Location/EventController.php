<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\AppContext;
use App\Cdn\EventPageCache;
use App\Controller\AbstractController as BaseController;
use App\Controller\Comment\CommentController;
use App\Entity\Comment;
use App\Entity\User;
use App\Form\Type\CommentType;
use App\Manager\EventRedirectManager;
use App\Manager\WidgetsManager;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Security\Voter\EventVoter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EventController extends BaseController
{
    // Shared by the CDN for visitors only (SharedCacheSubscriber); never by a browser, which would keep it after a login.
    // EventPageCache sets the lifetime of an event page (a day, a week once ended) and the tags that purge it;
    // Cloudflare serves the cached page while it asks again, or while the origin is down (a deploy)
    #[Cache(maxage: 0, smaxage: 86400, public: true, staleWhileRevalidate: 86400, staleIfError: 86400)]
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_event_details', methods: ['GET'])]
    #[Route(path: '/soiree/{slug<%patterns.slug%>}', name: 'app_event_details_old', methods: ['GET'])]
    public function index(AppContext $appContext, EventPageCache $eventPageCache, EventRedirectManager $eventRedirectManager, CommentRepository $commentRepository, UserRepository $userRepository, WidgetsManager $widgetsManager, string $slug, ?int $id = null): Response
    {
        $location = $appContext->getLocation();
        $event = $eventRedirectManager->getEvent($id, $slug, $location->getSlug(), 'app_event_details');

        // Those who can edit a draft (its author, an admin) preview it as it will be published; everyone else only
        // gets a notice, so the page needs none of the widgets below
        if (!$this->isGranted(EventVoter::VIEW, $event)) {
            return $eventPageCache->applyTo($this->render('location/event/index.html.twig', [
                'location' => $location,
                'event' => $event,
                'isHiddenDraft' => true,
            ]), $event);
        }

        // Canonical URL, shared by the "Partager" links
        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        // Widget data (first page only)
        $user = $this->getUser();
        \assert($user instanceof User || null === $user);
        $trendsData = $widgetsManager->getTrendsData($event, $user, $link);
        $nextEventsData = $widgetsManager->getNextEventsData($event, $location);
        $similarEventsData = $widgetsManager->getSimilarEventsData($event, $location);

        // Comments widget data (first page)
        $comments = $this->createMultipleEagerLoadingPaginator(
            $commentRepository->findAllByEventQueryBuilder($event),
            $commentRepository,
            1,
            CommentController::COMMENTS_PER_PAGE,
        );

        $commentForm = null;
        if ($this->isGranted('ROLE_USER')) {
            $comment = new Comment();
            $commentForm = $this->createForm(CommentType::class, $comment, [
                'action' => $this->generateUrl('app_comment_new', ['id' => $event->getId()]),
            ]);
        }

        $renderData = [
            'location' => $location,
            'event' => $event,
            'eventUrl' => $link,
            'isHiddenDraft' => false,
            // Faces beside the "J'y vais" button
            'participants' => $userRepository->findEventParticipants($event, 3),
            // Trends widget
            'trendsData' => $trendsData,
            // Similar events widget
            'similarEventsData' => $similarEventsData,
            // Comments widget
            'comments' => $comments,
            'commentForm' => $commentForm,
            'nextEventsData' => $nextEventsData,
        ];

        return $eventPageCache->applyTo($this->render('location/event/index.html.twig', $renderData), $event);
    }
}
