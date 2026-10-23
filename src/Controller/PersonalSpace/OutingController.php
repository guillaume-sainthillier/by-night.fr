<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Controller\AbstractController as BaseController;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The outings of a member, the one who goes out rather than the organizer ("Mes événements"): the events to come they
 * said they go to, soonest first. The past ones stay on their public profile. Their calendar app can subscribe to them
 * (OutingCalendarController), at an address only they know.
 */
final class OutingController extends BaseController
{
    private const int OUTINGS_PER_PAGE = 12;

    /** The CSRF token of the form giving the calendar a new address */
    private const string RENEW_CALENDAR_TOKEN = 'outing-calendar-renew';

    #[Route(path: '/mes-sorties', name: 'app_outing_list', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $user = $this->getAppUser();

        // The address of the calendar is shown here: made the first time
        if (null === $user->getCalendarToken()) {
            $user->renewCalendarToken();
            $this->getEntityManager()->flush();
        }

        $outings = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, true),
            $eventRepository,
            max(1, $request->query->getInt('page', 1)),
            self::OUTINGS_PER_PAGE,
            ['view' => 'events:agenda:list'],
        );

        return $this->render('personal-space/outings.html.twig', [
            'outings' => $outings,
            'pastCount' => max(0, $eventRepository->getUserFavoriteEventsCount($user) - $outings->getNbResults()),
            'calendarUrl' => $this->generateUrl('app_outing_calendar', ['token' => $user->getCalendarToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'renewCalendarToken' => self::RENEW_CALENDAR_TOKEN,
        ]);
    }

    /**
     * A new address for the calendar, for a member who shared the former one: the apps subscribed to it stop
     * receiving the outings.
     */
    #[Route(path: '/mes-sorties/calendrier', name: 'app_outing_calendar_renew', methods: ['POST'])]
    public function renewCalendar(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::RENEW_CALENDAR_TOKEN, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->getAppUser()->renewCalendarToken();
        $this->getEntityManager()->flush();
        $this->addFlash('success', "L'adresse de votre calendrier a bien été changée. Abonnez-vous de nouveau avec celle-ci.");

        return $this->redirectToRoute('app_outing_list', ['_fragment' => 'calendrier']);
    }
}
