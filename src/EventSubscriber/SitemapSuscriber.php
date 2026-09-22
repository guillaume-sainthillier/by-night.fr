<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Controller\Location\AgendaController;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\PageRepository;
use App\Repository\PlaceRepository;
use App\Repository\UserRepository;
use App\SEO\EventIndexingPolicy;
use DateTimeImmutable;
use DateTimeInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the sitemap from what deserves the crawl budget right now: listings and places with
 * upcoming events, and the events ending on or after the EventIndexingPolicy sitemap date.
 * Google ignores <priority> and <changefreq>, so being listed here is the actual signal; not
 * being listed does not remove a page from the index.
 */
final class SitemapSuscriber implements EventSubscriberInterface
{
    /**
     * A city needs a full agenda page of upcoming events before its per-category pages are
     * submitted: with fewer, most of those five pages would be empty listings.
     */
    public const int CATEGORY_PAGES_MIN_EVENTS = AgendaController::EVENT_PER_PAGE;

    private const array AGENDA_TYPES = ['concert', 'etudiant', 'famille', 'spectacle', 'exposition'];

    private UrlContainerInterface $urlContainer;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClockInterface $clock,
        private readonly EventIndexingPolicy $eventIndexingPolicy,
        private readonly CityRepository $cityRepository,
        private readonly PlaceRepository $placeRepository,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
        private readonly PageRepository $pageRepository,
    ) {
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
            'pages' => $this->registerPageRoutes(...),
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
        $cities = $this->cityRepository->findAllSitemap($this->today());

        foreach ($cities as $city) {
            $this->addUrl($section, 'app_location_index', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_index', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);

            if ((int) $city['nb'] < self::CATEGORY_PAGES_MIN_EVENTS) {
                continue;
            }

            foreach (self::AGENDA_TYPES as $type) {
                $this->addUrl($section, 'app_agenda_by_type', ['type' => $type, 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            }
        }
    }

    private function registerPlacesRoutes(?string $section): void
    {
        $places = $this->placeRepository->findAllSitemap($this->today());

        foreach ($places as $place) {
            $this->addUrl(
                $section,
                'app_agenda_by_place',
                [
                    'placeSlug' => $place['slug'],
                    'location' => $place['city_slug'],
                ],
                null,
                UrlConcrete::CHANGEFREQ_DAILY,
                0.6
            );
        }
    }

    private function registerEventRoutes(?string $section): void
    {
        $today = $this->today();
        $events = $this->eventRepository->findAllSiteMap($this->eventIndexingPolicy->getSitemapSince());

        foreach ($events as $event) {
            $isEventPast = $event['endDate'] < $today;
            $this->addUrl(
                $section,
                'app_event_details',
                [
                    'id' => $event['id'],
                    'slug' => $event['slug'],
                    'location' => $event['city_slug'] ?? $event['country_slug'] ?? 'unknown',
                ],
                $event['updatedAt'],
                $isEventPast ? UrlConcrete::CHANGEFREQ_WEEKLY : UrlConcrete::CHANGEFREQ_DAILY,
                $isEventPast ? 0.3 : 1.0
            );
        }
    }

    private function registerUserRoutes(?string $section): void
    {
        $users = $this->userRepository->findAllSitemap($this->today());

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

    private function registerPageRoutes(?string $section): void
    {
        $pages = $this->pageRepository->findAllSitemap();

        foreach ($pages as $page) {
            $this->addUrl(
                $section,
                'app_page_show',
                ['slug' => $page['slug']],
                $page['updatedAt'],
                UrlConcrete::CHANGEFREQ_MONTHLY,
                0.5
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

    private function today(): DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
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
