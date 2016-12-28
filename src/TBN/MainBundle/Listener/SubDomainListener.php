<?php

namespace TBN\MainBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use TBN\MainBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;

class SubDomainListener
{
    /**
     * @var SiteManager
     */
    private $siteManager;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var RouterInterface
     */
    private $router;

    private $baseHost;
    private $basePort;

    public function __construct(SiteManager $siteManager, EntityManager $em, RouterInterface $router, $baseHost, $basePort)
    {
        $this->siteManager = $siteManager;
        $this->router = $router;
        $this->em = $em;
        $this->baseHost = $baseHost;
        $this->basePort = $basePort;
    }

    public function onDomainParse(GetResponseEvent $event)
    {
        //Chargement du site
        if ($this->siteManager->getCurrentSite() === null) {
            $request = $event->getRequest();
            $currentHost = $request->getHttpHost();

            $subdomain = \str_replace([
                '.' . $this->baseHost . ':' . $this->basePort,
                '.' . $this->baseHost . ':' . $this->basePort,
                'www.' . $this->baseHost,
                'www.' . $this->baseHost
            ], '', $currentHost);


            if ($subdomain === $this->baseHost) {
                return;
            }

            $site = $this->em
                ->getRepository('TBNMainBundle:Site')
                ->findOneBy(['subdomain' => $subdomain]);

            if (!$site || !$site->isActif()) {
                $response = new RedirectResponse($this->router->generate('tbn_main_index'));
                $event->setResponse($response);
            } else {
                $this->siteManager->setCurrentSite($site);
            }
        }
    }
}
