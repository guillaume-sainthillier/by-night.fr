<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 20:29.
 */

namespace AppBundle\EventListener;

use Doctrine\Common\Persistence\ObjectManager;
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
     * @var ObjectManager
     */
    private $manager;

    /**
     * @var UrlContainerInterface
     */
    private $urlContainer = null;

    /**
     * @var \DateTime
     */
    private $now;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     * @param ObjectManager         $manager
     */
    public function __construct(UrlGeneratorInterface $urlGenerator, ObjectManager $manager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->manager      = $manager;
        $this->now          = new \DateTime();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            SitemapPopulateEvent::ON_SITEMAP_POPULATE => 'registerRoutes',
        );
    }

    public function registerRoutes(SitemapPopulateEvent $event)
    {
        $this->urlContainer = $event->getUrlContainer();
        $section            = $event->getSection();

        $sections = [
            'app'    => [$this, 'registerStaticRoutes'],
            'agenda' => [$this, 'registerAgendaRoutes'],
            'users'  => [$this, 'registerUserRoutes'],
//            'events' => [$this, 'registerEventRoutes']
        ];

        foreach ($sections as $name => $generateFunction) {
            if (!$section || $name === $section) {
                call_user_func($generateFunction, $name);
            }
        }
    }

    private function registerAgendaRoutes($section)
    {
        $sites = $this->manager->getRepository('AppBundle:Site')->findAll();

        foreach ($sites as $site) {
            $this->addUrl($section, 'tbn_agenda_agenda', ['city' => $site->getSubdomain()]);
            $this->addUrl($section, 'tbn_agenda_sortir', ['type' => 'concert', 'city' => $site->getSubdomain()]);
            $this->addUrl($section, 'tbn_agenda_sortir', ['type' => 'etudiant', 'city' => $site->getSubdomain()]);
            $this->addUrl($section, 'tbn_agenda_sortir', ['type' => 'famille', 'city' => $site->getSubdomain()]);
            $this->addUrl($section, 'tbn_agenda_sortir', ['type' => 'spectacle', 'city' => $site->getSubdomain()]);
            $this->addUrl($section, 'tbn_agenda_sortir', ['type' => 'exposition', 'city' => $site->getSubdomain()]);
        }

        $places = $this->manager->getRepository('AppBundle:Place')->findAll();
        foreach ($places as $place) {
            $this->addUrl($section, 'tbn_agenda_place', ['slug' => $place->getSlug(), 'city' => $place->getSite()->getSubdomain()]);
        }

        $events = [];
        foreach ($events as $event) {
            $this->addUrl($section, 'tbn_agenda_details', [
                'id'   => $event['id'],
                'slug' => $event['slug'],
                'city' => $event['subdomain'],
            ]);
        }
    }

    private function registerEventRoutes($section)
    {
        $events = $this->manager->getRepository('AppBundle:Agenda')->findSiteMap();

        foreach ($events as $event) {
            $this->addUrl($section, 'tbn_agenda_details', [
                'id'   => $event['id'],
                'slug' => $event['slug'],
                'city' => $event['subdomain'],
            ]);
        }

        //TODO: add tags to sitemap
    }

    private function registerUserRoutes($section)
    {
        $users = $this->manager->getRepository('AppBundle:User')->findAll();

        foreach ($users as $user) {
            $this->addUrl($section, 'tbn_user_details', [
                'id'   => $user->getId(),
                'slug' => $user->getSlug(),
            ], $user->getUpdatedAt());
        }
    }

    private function registerStaticRoutes($section)
    {
        $staticRoutes = [
            'tbn_main_index',
            'tbn_agenda_about',
            'tbn_agenda_plus',
            'tbn_main_cookie',
            'fos_user_security_login',
            'fos_user_registration_register',
        ];

        foreach ($staticRoutes as $route) {
            $this->addUrl($section, $route);
        }
    }

    private function addUrl($section, $name, array $params = [], \DateTime $lastMod = null)
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
