<?php

namespace AppBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use AppBundle\Site\SiteManager;
use Doctrine\ORM\EntityManager;

class CityListener
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

    public function __construct(SiteManager $siteManager, EntityManager $em, RouterInterface $router)
    {
        $this->siteManager = $siteManager;
        $this->router = $router;
        $this->em = $em;
    }

    public function onDomainParse(GetResponseEvent $event)
    {
        //Chargement du site
        if ($this->siteManager->getCurrentSite() === null) {
            $request = $event->getRequest();

            dump($event);
            die;
            if (!$request->attributes->has('city')) {
                return;
            }

            die('OK');
            $city = $request->attributes->get('city');

            $site = $this->em
                ->getRepository('AppBundle:Site')
                ->findOneBy(['subdomain' => $city]);

            if (!$site || !$site->isActif()) {
                $response = new RedirectResponse($this->router->generate('tbn_main_index'));
                $event->setResponse($response);
            } else {
                $this->siteManager->setCurrentSite($site);
            }
        }
    }
}
