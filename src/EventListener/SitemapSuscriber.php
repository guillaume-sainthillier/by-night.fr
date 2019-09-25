<?php

namespace App\EventListener;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Service\UrlContainerInterface;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapSuscriber implements EventSubscriberInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ManagerRegistry
     */
    private $doctrine;

    /**
     * @var UrlContainerInterface
     */
    private $urlContainer = null;

    /**
     * @var DateTime
     */
    private $now;

    public function __construct(UrlGeneratorInterface $urlGenerator, ManagerRegistry $doctrine)
    {
        $this->urlGenerator = $urlGenerator;
        $this->doctrine = $doctrine;
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
        ];

        foreach ($sections as $name => $generateFunction) {
            if (!$section || $name === $section) {
                \call_user_func($generateFunction, $name);
            }
        }
    }

    private function registerAgendaRoutes($section)
    {
        $cities = $this->doctrine->getRepository(City::class)->findSiteMap();

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
        $places = $this->doctrine->getRepository(Place::class)->findSiteMap();

        foreach ($places as $place) {
            $place = current($place);
            $this->addUrl($section, 'app_agenda_place', [
                'slug' => $place['slug'],
                'location' => $place['city_slug']
            ]);
        }
    }

    private function registerEventRoutes($section)
    {
        $events = $this->doctrine->getRepository(Event::class)->findSiteMap();

        foreach ($events as $event) {
            $event = current($event);
            $this->addUrl($section, 'app_event_details', [
                'id' => $event['id'],
                'slug' => $event['slug'],
                'location' => $event['city_slug'] ?: ($event['country_slug'] ?: 'unknown'),
            ]);
        }
    }

    private function registerUserRoutes($section)
    {
        $users = $this->doctrine->getRepository(User::class)->findSiteMap();
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
            'app_agenda_programme_tv',
        ];

        foreach ($staticRoutes as $route) {
            $this->addUrl($section, $route);
        }
    }

    private function addUrl($section, $name, array $params = [], DateTime $lastMod = null)
    {
        $url = $this->urlGenerator->generate($name, $params, UrlGeneratorInterface::ABSOLUTE_URL);

        $url = new UrlConcrete(
            $url,
            $lastMod ?: $this->now,
            UrlConcrete::CHANGEFREQ_HOURLY,
            1
        );

        $this->urlContainer->addUrl($url, $section);
    }
}
