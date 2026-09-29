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
use App\Controller\AbstractController as BaseController;
use App\Controller\Comment\CommentController;
use App\Entity\Comment;
use App\Entity\User;
use App\Form\Type\CommentType;
use App\Manager\EventRedirectManager;
use App\Manager\WidgetsManager;
use App\Picture\EventProfilePicture;
use App\Repository\CommentRepository;
use App\Repository\UserRepository;
use App\Security\Voter\EventVoter;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EventController extends BaseController
{
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_event_details', methods: ['GET'])]
    #[Route(path: '/soiree/{slug<%patterns.slug%>}', name: 'app_event_details_old', methods: ['GET'])]
    public function index(AppContext $appContext, EventRedirectManager $eventRedirectManager, EventProfilePicture $eventProfilePicture, CommentRepository $commentRepository, UserRepository $userRepository, WidgetsManager $widgetsManager, string $slug, ?int $id = null): Response
    {
        $location = $appContext->getLocation();
        $event = $eventRedirectManager->getEvent($id, $slug, $location->getSlug(), 'app_event_details');

        // Those who can edit a draft (its author, an admin) preview it as it will be published; everyone else only
        // gets a notice, so the page needs none of the widgets below
        if (!$this->isGranted(EventVoter::VIEW, $event)) {
            return $this->render('location/event/index.html.twig', [
                'location' => $location,
                'event' => $event,
                'isHiddenDraft' => true,
            ]);
        }

        // Build Page object for social sharing
        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $eventProfile = $eventProfilePicture->getOriginalPicture($event);
        // Every field as a string: Page normalizes each one with preg_replace(), which deprecates null
        // (the library's own defaults for icon and twitterUser included)
        $page = new Page([
            'url' => $link,
            'title' => $event->getName() ?? '',
            'text' => $event->getDescription() ?? '',
            'image' => $eventProfile,
            'icon' => '',
            'twitterUser' => '',
        ]);

        // Widget data (first page only)
        $user = $this->getUser();
        \assert($user instanceof User || null === $user);
        $trendsData = $widgetsManager->getTrendsData($event, $user, $page);
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

        return $this->render('location/event/index.html.twig', $renderData);
    }
}
