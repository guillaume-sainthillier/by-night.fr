<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Controller\AbstractController as BaseController;
use App\Entity\UserEvent;
use App\Repository\UserEventRepository;
use App\Repository\UserRepository;
use App\Transfer\Export\EventCalendarWriter;
use DateInterval;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A member's outings as a calendar their calendar app subscribes to ("Mes sorties", OutingController shows its
 * address): the events they go to (CONFIRMED) or are interested in (TENTATIVE), from a month ago on. The app reads it
 * again on its own, without the member's session: the secret in its address is the only key, so the route never opens
 * a session (stateless) and nothing but the member's app keeps it.
 */
final class OutingCalendarController extends BaseController
{
    /** The outings already over that the calendar keeps: the app would otherwise drop last weekend's */
    private const string PAST = '-30 days';

    /** How often the app should read the calendar again (X-PUBLISHED-TTL; Google keeps its own pace) */
    private const string REFRESH = 'PT3H';

    /** How long a client may keep a response before asking again: an app that reads it twice in a row */
    private const int MAX_AGE = 900;

    #[Route(path: '/calendrier/{token}.ics', name: 'app_outing_calendar', requirements: ['token' => '[A-Za-z0-9_-]{32}'], methods: ['GET'], stateless: true)]
    public function index(string $token, UserRepository $userRepository, UserEventRepository $userEventRepository, EventCalendarWriter $calendarWriter, ClockInterface $clock): Response
    {
        $user = $userRepository->findOneBy(['calendarToken' => $token]);
        if (null === $user || !$user->isEnabled()) {
            throw new NotFoundHttpException('No calendar answers to this address.');
        }

        $outings = $userEventRepository->findCalendarFeed($user, $clock->now()->modify(self::PAST));
        $tentative = array_values(array_filter(array_map(
            static fn (UserEvent $outing): ?int => $outing->isGoing() ? null : $outing->getEvent()?->getId(),
            $outings,
        )));

        $calendar = $calendarWriter->render(
            array_filter(array_map(static fn (UserEvent $outing) => $outing->getEvent(), $outings)),
            'Mes sorties By Night',
            $tentative,
            new DateInterval(self::REFRESH),
        );

        $response = new Response($calendar, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="mes-sorties-by-night.ics"',
            // A secret address: never in a search engine, whoever came across it
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
        $response->setPrivate();
        $response->setMaxAge(self::MAX_AGE);

        return $response;
    }
}
