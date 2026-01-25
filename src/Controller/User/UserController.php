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
use App\Entity\User;
use App\Event\Events;
use App\Event\UserCheckUrlEvent;
use App\Repository\EventRepository;
use DateTime;
use IntlDateFormatter;
use Locale;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/membres')]
final class UserController extends BaseController
{
    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_user_index', methods: ['GET'])]
    #[Route(path: '/{username<%patterns.slug%>}', name: 'app_user_index_old', methods: ['GET'])]
    public function index(EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, ?int $id = null, ?string $slug = null, ?string $username = null): Response
    {
        $userCheck = new UserCheckUrlEvent($id, $slug, $username, 'app_user_index');
        $eventDispatcher->dispatch($userCheck, Events::CHECK_USER_URL);
        if (null !== $userCheck->getResponse()) {
            return $userCheck->getResponse();
        }

        $user = $userCheck->getUser();

        return $this->render('user/index.html.twig', [
            'user' => $user,
            'nextEvents' => $eventRepository->findAllNextEvents($user),
            'previousEvents' => $eventRepository->findAllNextEvents($user, false),
            'places' => $eventRepository->findAllUserPlaces($user),
            'favoriteEventsCount' => $eventRepository->getUserFavoriteEventsCount($user),
        ]);
    }

    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}/stats/{type}', name: 'app_user_stats', requirements: ['type' => 'semaine|mois|annee'], methods: ['GET'])]
    #[Route(path: '/{username<%patterns.slug%>}/stats/{type}', name: 'app_user_stats_old', requirements: ['type' => 'semaine|mois|annee'], methods: ['GET'])]
    public function stats(EventDispatcherInterface $eventDispatcher, EventRepository $eventRepository, string $type, ?int $id = null, ?string $slug = null, ?string $username = null): Response
    {
        $datas = null;
        $userCheck = new UserCheckUrlEvent($id, $slug, $username, 'app_user_stats', ['type' => $type]);
        $eventDispatcher->dispatch($userCheck, Events::CHECK_USER_URL);
        if (null !== $userCheck->getResponse()) {
            return $userCheck->getResponse();
        }

        $user = $userCheck->getUser();
        $datas = match ($type) {
            'semaine' => $this->getDataOfWeek($eventRepository, $user),
            'mois' => $this->getDataOfMonth($eventRepository, $user),
            'annee' => $this->getDataOfYear($eventRepository, $user),
            default => new JsonResponse($datas),
        };

        return new JsonResponse($datas);
    }

    /**
     * @return array[]
     *
     * @psalm-return array<array>
     */
    private function getDataOfWeek(EventRepository $repo, User $user): array
    {
        $datas = $repo->getStatsUser($user, 'DAYOFWEEK');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        foreach (range(1, 7) as $day) {
            $date = new DateTime('0' . $day . '-01-' . date('Y'));
            $dayNumber = $date->format('w');
            $dateFormatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE
            );

            $dateFormatter->setPattern('EEE');
            $final_datas['categories'][$dayNumber] = $dateFormatter->format($date);
            $dateFormatter->setPattern('EEEE');
            $final_datas['full_categories'][$dayNumber] = $dateFormatter->format($date);
        }

        ksort($final_datas['categories']);
        ksort($final_datas['full_categories']);

        return $this->fillDatas($final_datas, $datas);
    }

    /**
     * @return array[]
     */
    private function fillDatas(array $final_datas, array $datas): array
    {
        foreach (array_keys($final_datas['categories']) as $key) {
            $final_datas['data'][$key] = $datas[$key] ?? 0;
        }

        return array_map(array_values(...), $final_datas);
    }

    /**
     * @return array[]
     *
     * @psalm-return array<array>
     */
    private function getDataOfMonth(EventRepository $repo, User $user): array
    {
        $datas = $repo->getStatsUser($user, 'MONTH');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        foreach (range(1, 12) as $month) {
            $date = new DateTime('01-' . $month . '-' . date('Y'));
            $dateFormatter = IntlDateFormatter::create(
                Locale::getDefault(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE
            );

            $dateFormatter->setPattern('MMM');
            $final_datas['categories'][$month] = $dateFormatter->format($date);
            $dateFormatter->setPattern('MMMM');
            $final_datas['full_categories'][$month] = $dateFormatter->format($date);
        }

        return $this->fillDatas($final_datas, $datas);
    }

    /**
     * @return array[]
     *
     * @psalm-return array<array>
     */
    private function getDataOfYear(EventRepository $repo, User $user): array
    {
        $datas = $repo->getStatsUser($user, 'YEAR');

        $final_datas = [
            'categories' => [],
            'data' => [],
            'full_categories' => [],
        ];

        if ([] !== $datas) {
            $minYear = min(array_keys($datas));
            $maxYear = max(array_keys($datas));
        } else {
            $minYear = (int) $user->getCreatedAt()->format('Y');
            $maxYear = (int) date('Y');
        }

        foreach (range($minYear, $maxYear) as $year) {
            $final_datas['categories'][$year] = $year;
            $final_datas['full_categories'][$year] = $year;
        }

        return $this->fillDatas($final_datas, $datas);
    }
}
