<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapSuscriber implements EventSubscriberInterface
{
    private UrlContainerInterface $urlContainer;

    private readonly DateTimeInterface $now;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CityRepository $cityRepository,
        private readonly PlaceRepository $placeRepository,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
    ) {
        $this->now = new DateTimeImmutable();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SitemapPopulateEvent::class => 'registerRoutes',
        ];
    }

    public function registerRoutes(SitemapPopulateEvent $event): void
    {
        $this->urlContainer = $event->getUrlContainer();
        $section = $event->getSection();

        $sections = [
            'app' => $this->registerStaticRoutes(...),
            'agenda' => $this->registerAgendaRoutes(...),
            'places' => $this->registerPlacesRoutes(...),
            'users' => $this->registerUserRoutes(...),
            'events' => $this->registerEventRoutes(...),
            'tags' => $this->registerTagRoutes(...),
        ];

        foreach ($sections as $name => $generateFunction) {
            if (!$section || $name === $section) {
                \call_user_func($generateFunction, $name);
            }
        }
    }

    private function registerTagRoutes(?string $section): void
    {
        $tags = $this->cityRepository->findAllTagsSitemap();

        $seen = [];
        foreach ($tags as $tag) {
            $key = $tag['citySlug'] . '-' . $tag['tagId'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $this->addUrl(
                $section,
                'app_agenda_by_tag',
                [
                    'location' => $tag['citySlug'],
                    'tagSlug' => $tag['tagSlug'],
                    'tagId' => $tag['tagId'],
                ],
                null,
                UrlConcrete::CHANGEFREQ_DAILY,
                0.8
            );
        }
    }

    private function registerAgendaRoutes(?string $section): void
    {
        $cities = $this->cityRepository->findAllSitemap();

        foreach ($cities as $city) {
            $this->addUrl($section, 'app_agenda_index', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_index', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_by_type', ['type' => 'concert', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_by_type', ['type' => 'etudiant', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_by_type', ['type' => 'famille', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_by_type', ['type' => 'spectacle', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_by_type', ['type' => 'exposition', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
        }
    }

    private function registerPlacesRoutes(?string $section): void
    {
        $places = $this->placeRepository->findAllSitemap();

        foreach ($places as $place) {
            $this->addUrl(
                $section,
                'app_agenda_by_place',
                [
                    'slug' => $place['slug'],
                    'location' => $place['city_slug'],
                ],
                null,
                UrlConcrete::CHANGEFREQ_NEVER,
                0.1
            );
        }
    }

    private function registerEventRoutes(?string $section): void
    {
        $events = $this->eventRepository->findAllSiteMap();

        foreach ($events as $event) {
            $isEventPast = $event['endDate'] < $this->now;
            $this->addUrl(
                $section,
                'app_event_details',
                [
                    'id' => $event['id'],
                    'slug' => $event['slug'],
                    'location' => $event['city_slug'] ?? $event['country_slug'] ?? 'unknown',
                ],
                $event['updatedAt'],
                $isEventPast ? UrlConcrete::CHANGEFREQ_NEVER : UrlConcrete::CHANGEFREQ_DAILY,
                $isEventPast ? 0.1 : 1.0
            );
        }
    }

    private function registerUserRoutes(?string $section): void
    {
        $users = $this->userRepository->findAllSitemap();
        foreach ($users as $user) {
            $this->addUrl(
                $section,
                'app_user_index',
                [
                    'id' => $user['id'],
                    'slug' => $user['slug'],
                ],
                $user['updatedAt'],
                UrlConcrete::CHANGEFREQ_DAILY,
                0.4
            );
        }
    }

    private function registerStaticRoutes(?string $section): void
    {
        $staticRoutes = [
            'app_search_index',
            'app_index',
            'app_about',
            'app_plus',
            'app_main_cookie',
            'app_legal_mentions',
        ];

        foreach ($staticRoutes as $route) {
            $this->addUrl($section, $route);
        }
    }

    private function addUrl(string $section, string $name, array $params = [], ?DateTimeInterface $lastMod = null, ?string $changefreq = null, float $priority = 0.6): void
    {
        $url = $this->urlGenerator->generate($name, $params, UrlGeneratorInterface::ABSOLUTE_URL);

        $url = new UrlConcrete(
            $url,
            $lastMod,
            $changefreq ?: UrlConcrete::CHANGEFREQ_DAILY,
            $priority
        );

        $this->urlContainer->addUrl($url, $section);
    }
}
