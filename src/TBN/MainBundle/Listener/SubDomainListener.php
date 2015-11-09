<?php

namespace TBN\MainBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TBN\MainBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;

class SubDomainListener {

    private $siteManager;
    private $em;
    private $baseHost;
    
    public function __construct(SiteManager $siteManager, EntityManager $em, $baseHost) {
        $this->siteManager = $siteManager;
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

            $subdomain = \str_replace('.' . $this->baseHost, '', $currentHost);

            if ($subdomain === $this->baseHost) {
                return;
            }

            $site = $this->em
                ->getRepository('TBNMainBundle:Site')
                ->findOneBy(['subdomain' => $subdomain]);                  
                
            if (!$site || !$site->isActif()) {
                throw new NotFoundHttpException(sprintf(
                        'Le sous domaine "%s" est introuvable sur "%s"', $subdomain, $this->baseHost
                ));
            }
            
            $this->siteManager->setCurrentSite($site);
        }            
    }
}
