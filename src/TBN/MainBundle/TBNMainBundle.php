<?php

namespace TBN\MainBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TBN\MainBundle\DependencyInjection\Compiler\OverrideServiceCompilerPass;

class TBNMainBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new OverrideServiceCompilerPass());
    }

    public function boot()
    {
        $router         = $this->container->get('router');
        $event          = $this->container->get('event_dispatcher');
        $siteManager    = $this->container->get('site_manager');
        $em             = $this->container->get('doctrine');

        //listen presta_sitemap.populate event
        $event->addListener(
            SitemapPopulateEvent::ON_SITEMAP_POPULATE,
            function(SitemapPopulateEvent $event) use ($router, $siteManager, $em){
                //CLI
                if(null !== $event->getSection()) {
                    $sites = $em->getRepository('TBNMainBundle:Site')->findBy(['subdomain' => $event->getSection()]);
                    $site   = isset($sites[0]) ? $sites[0] : null;
                }else {
                    $site = $siteManager->getCurrentSite();
                }
                
                $params = [];
                if(null === $site) {
                    $routes = [
                        'tbn_main_index',
                        'tbn_main_cookie'
                    ];
                }else {
                    $params['subdomain'] = $site->getSubdomain();
                    $routes = [
                        'tbn_agenda_index',
                        'tbn_agenda_agenda',
                        'tbn_agenda_mention_legales',
                        'tbn_agenda_about',
                        'tbn_agenda_plus'
                    ];
                    
                    $agendas = $em->getRepository('TBNAgendaBundle:Agenda')->findBy(['site' => $site->getId()]);
                    foreach($agendas as $agenda) {
                        $url = $router->generate('tbn_agenda_details', ['subdomain' => $site->getSubdomain(), 'slug' => $agenda->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
                        $event->getGenerator()->addUrl(
                            new UrlConcrete(
                                $url,
                                $agenda->getDateModification(),
                                UrlConcrete::CHANGEFREQ_HOURLY,
                                1
                            ),
                            $site->getSubdomain()
                        );
                    }
                }                              
                foreach($routes as $route) {
                    $url = $router->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
                    
                    $event->getGenerator()->addUrl(
                        new UrlConcrete(
                            $url,
                            new \DateTime(),
                            UrlConcrete::CHANGEFREQ_HOURLY,
                            1
                        ),
                        null !== $site ? $site->getSubdomain() : 'default'
                    );
                }
            }
        );
    }
}
