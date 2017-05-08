<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 03/05/2017
 * Time: 20:29
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
     * @param UrlGeneratorInterface $urlGenerator
     * @param ObjectManager         $manager
     */
    public function __construct(UrlGeneratorInterface $urlGenerator, ObjectManager $manager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->manager = $manager;
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
        $this->registerStaticRoutes();
        $this->registerEventRoutes();
    }

    private function registerEventRoutes() {
        $events = $this->manager->getRepository("AppBundle:Agenda")->findSiteMap();

        foreach($events as $event) {
            $this->addUrl("events", "tbn_agenda_details", [
                "id" => $event['id'],
                "slug" => $event['slug'],
                "city" => $event['subdomain'],
            ]);
        }
    }

    private function registerStaticRoutes() {
        $staticRoutes = [
            'tbn_main_index',
            'tbn_agenda_about',
            'tbn_agenda_plus',
            'tbn_main_cookie'
        ];

        foreach($staticRoutes as $route) {
            $this->addUrl("app", $route);
        }
    }

    private function addUrl($section, $name, array $params = []) {
        $url = $this->urlGenerator->generate($name, $params, UrlGeneratorInterface::ABSOLUTE_URL);

        $url = new UrlConcrete(
            $url,
            new \DateTime(),
            UrlConcrete::CHANGEFREQ_HOURLY,
            1
        );

        $this->urlContainer->addUrl($url, $section);
    }
}
