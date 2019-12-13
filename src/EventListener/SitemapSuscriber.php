<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapSuscriber implements EventSubscriberInterface
{
    private const ITEMS_PER_PAGE = 5000;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ManagerRegistry
     */
    private $entityManager;

    /**
     * @var UrlContainerInterface
     */
    private $urlContainer = null;

    /**
     * @var DateTime
     */
    private $now;

    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->now = new DateTime();
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
        $events = $this->entityManager->getRepository(City::class)->findTagSiteMap();

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
                $this->addUrl($section, 'app_agenda_tags', ['location' => $event['slug'], 'tag' => $tag], null, UrlConcrete::CHANGEFREQ_DAILY);
            }
            $lastSlug = $slug;
        }
    }

    private function addUrl($section, $name, array $params = [], DateTime $lastMod = null, string $changefreq = null)
    {
        $url = $this->urlGenerator->generate($name, $params, UrlGeneratorInterface::ABSOLUTE_URL);

        $url = new UrlConcrete(
            $url,
            $lastMod ?: $this->now,
            $changefreq ?: UrlConcrete::CHANGEFREQ_HOURLY,
            1
        );

        $this->urlContainer->addUrl($url, $section);
    }

    private function registerAgendaRoutes($section)
    {
        $cities = $this->entityManager->getRepository(City::class)->findSiteMap();

        foreach ($cities as $city) {
            $city = current($city);
            $this->addUrl($section, 'app_agenda_index', ['location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_agenda', ['location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'concert', 'location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'etudiant', 'location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'famille', 'location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'spectacle', 'location' => $city['slug']]);
            $this->addUrl($section, 'app_agenda_sortir', ['type' => 'exposition', 'location' => $city['slug']]);
        }
    }

    private function registerPlacesRoutes($section)
    {
        $places = $this->entityManager->getRepository(Place::class)->findSiteMap();

        foreach ($places as $place) {
            $place = current($place);
            $this->addUrl($section, 'app_agenda_place', [
                'slug' => $place['slug'],
                'location' => $place['city_slug'],
            ]);
        }
    }

    private function registerEventRoutes($section)
    {
        $nbEvents = $this->entityManager->getRepository(Event::class)->findSiteMapCount();
        $nbPages = ceil($nbEvents / self::ITEMS_PER_PAGE);

        for ($i = 0; $i < $nbPages; ++$i) {
            $events = $this->entityManager->getRepository(Event::class)->findSiteMap($i, self::ITEMS_PER_PAGE);

            foreach ($events as $event) {
                $event = current($event);
                $this->addUrl($section, 'app_event_details', [
                    'id' => $event['id'],
                    'slug' => $event['slug'],
                    'location' => $event['city_slug'] ?: ($event['country_slug'] ?: 'unknown'),
                ], DateTime::createFromImmutable($event['updatedAt']), $event['dateFin'] < $this->now ? UrlConcrete::CHANGEFREQ_NEVER : null);
            }
        }
    }

    private function registerUserRoutes($section)
    {
        $users = $this->entityManager->getRepository(User::class)->findSiteMap();
        foreach ($users as $user) {
            /** @var User $user */
            $user = $user[0];
            $this->addUrl($section, 'app_user_details', [
                'id' => $user->getId(),
                'slug' => $user->getSlug(),
            ], DateTime::createFromImmutable($user->getUpdatedAt()));
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
}
