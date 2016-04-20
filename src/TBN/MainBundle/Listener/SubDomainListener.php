<?php

namespace TBN\MainBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use TBN\MainBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;

class SubDomainListener {

    private $siteManager;
    private $em;
    private $baseHost;
    private $router;
    
    public function __construct(SiteManager $siteManager, EntityManager $em, RouterInterface $router, $baseHost) {
        $this->siteManager = $siteManager;
        $this->router = $router;
        $this->em = $em;
        $this->baseHost = $baseHost;
    }

    public function onDomainParse(GetResponseEvent $event) {
        if($this->siteManager->getSiteInfo() === null) {
            $siteInfo = $this->em
                ->getRepository('TBNUserBundle:SiteInfo')
                ->findOneBy([]);
                
            $this->siteManager->setSiteInfo($siteInfo);
        }        
        
        //Chargement du site
        if ($this->siteManager->getCurrentSite() === null) {
            $request = $event->getRequest();
            $currentHost = $request->getHttpHost();

            $subdomain = \str_replace(['.' . $this->baseHost, 'www.' . $this->baseHost], '', $currentHost);

            if ($subdomain === $this->baseHost) {
                return;
            }

            $site = $this->em
                ->getRepository('TBNMainBundle:Site')
                ->findOneBy(['subdomain' => $subdomain]);                  
                
            if (!$site || !$site->isActif()) {
                $response = new RedirectResponse($this->router->generate('tbn_main_index'));
                $event->setResponse($response);
            }else {
                $this->siteManager->setCurrentSite($site);
            }
        }            
    }
}
