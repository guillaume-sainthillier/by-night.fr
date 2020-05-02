<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Entity\User;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\PlaceRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapSuscriber implements EventSubscriberInterface
{
    private const ITEMS_PER_PAGE = 5_000;

    private UrlGeneratorInterface $urlGenerator;

    private EntityManagerInterface $entityManager;

    private ?UrlContainerInterface $urlContainer = null;

    private DateTime $now;
    /**
     * @var \App\Repository\CityRepository
     */
    private $cityRepository;
    /**
     * @var \App\Repository\PlaceRepository
     */
    private $placeRepository;
    /**
     * @var \App\Repository\EventRepository
     */
    private $eventRepository;
    /**
     * @var \App\Repository\UserRepository
     */
    private $userRepository;

    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager, CityRepository $cityRepository, PlaceRepository $placeRepository, EventRepository $eventRepository, UserRepository $userRepository)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->now = new DateTime();
        $this->cityRepository = $cityRepository;
        $this->placeRepository = $placeRepository;
        $this->eventRepository = $eventRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            SitemapPopulateEvent::ON_SITEMAP_POPULATE => 'registerRoutes',
        ];
    }

    public function registerRoutes(SitemapPopulateEvent $event)
    {
        $this->urlContainer = $event->getUrlContainer();
        $section = $event->getSection();

        $sections = [
            'app' => [$this, 'registerStaticRoutes'],
            'agenda' => [$this, 'registerAgendaRoutes'],
            'places' => [$this, 'registerPlacesRoutes'],
            'users' => [$this, 'registerUserRoutes'],
            'events' => [$this, 'registerEventRoutes'],
            'tags' => [$this, 'registerTagRoutes'],
        ];

        foreach ($sections as $name => $generateFunction) {
            if (!$section || $name === $section) {
                \call_user_func($generateFunction, $name);
            }
        }
    }

    private function registerTagRoutes($section)
    {
        $events = $this->cityRepository->findTagSiteMap();

        $cache = [];
        $lastSlug = null;
        foreach ($events as $event) {
            $event = current($event);
            $slug = $event['slug'];
            if ($slug !== $lastSlug) {
                unset($cache); //call GC
                $cache = [];
            }

            $tags = $event['categorieManifestation'] . ',' . $event['typeManifestation'] . ',' . $event['themeManifestation'];
            $tags = \array_unique(\array_map('trim', \array_map('ucfirst', \array_filter(\preg_split('#[,/]#', $tags)))));

            foreach ($tags as $tag) {
                if (!empty($cache[$tag])) {
                    continue;
                }

                $cache[$tag] = true;
                $this->addUrl(
                    $section,
                    'app_agenda_tags',
                    [
                        'location' => $event['slug'],
                        'tag' => $tag,
                    ],
                    null,
                    UrlConcrete::CHANGEFREQ_DAILY,
                    0.8
                );
            }
            $lastSlug = $slug;
        }
    }

    private function registerAgendaRoutes($section)
    {
        $cities = $this->cityRepository->findSiteMap();

        foreach ($cities as $city) {
            $city = current($city);
            $this->addUrl($section, 'app_agenda_index', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_agenda', ['location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'concert', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'etudiant', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'famille', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'spectacle', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'exposition', 'location' => $city['slug']], null, UrlConcrete::CHANGEFREQ_DAILY, 0.8);
        }
    }

    private function registerPlacesRoutes($section)
    {
        $places = $this->placeRepository->findSiteMap();

        foreach ($places as $place) {
            $place = current($place);
            $this->addUrl(
                $section,
                'app_agenda_place',
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

    private function registerEventRoutes($section)
    {
        $nbEvents = $this->eventRepository->findSiteMapCount();
        $nbPages = ceil($nbEvents / self::ITEMS_PER_PAGE);

        for ($i = 0; $i < $nbPages; ++$i) {
            $events = $this->eventRepository->findSiteMap($i, self::ITEMS_PER_PAGE);

            foreach ($events as $event) {
                $event = current($event);
                $this->addUrl(
                    $section,
                    'app_event_details',
                    [
                        'id' => $event['id'],
                        'slug' => $event['slug'],
                        'location' => $event['city_slug'] ?: ($event['country_slug'] ?: 'unknown'),
                    ],
                    DateTime::createFromImmutable($event['updatedAt']),
                    $event['dateFin'] < $this->now ? UrlConcrete::CHANGEFREQ_NEVER : UrlConcrete::CHANGEFREQ_DAILY,
                    $event['dateFin'] < $this->now ? 0.1 : 1.0
                );
            }
        }
    }

    private function registerUserRoutes($section)
    {
        $users = $this->userRepository->findSiteMap();
        foreach ($users as $user) {
            /** @var User $user */
            $user = $user[0];
            $this->addUrl(
                $section,
                'app_user_details',
                [
                    'id' => $user->getId(),
                    'slug' => $user->getSlug(),
                ],
                DateTime::createFromImmutable($user->getUpdatedAt()),
                UrlConcrete::CHANGEFREQ_DAILY,
                0.4
            );
        }
    }

    private function registerStaticRoutes($section)
    {
        $staticRoutes = [
            'app_search_query',
            'app_main_index',
            'app_about',
            'app_plus',
            'app_main_cookie',
            'app_mentions_legales',
        ];

        foreach ($staticRoutes as $route) {
            $this->addUrl($section, $route);
        }
    }

    private function addUrl($section, $name, array $params = [], DateTime $lastMod = null, string $changefreq = null, float $priority = 0.6)
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
