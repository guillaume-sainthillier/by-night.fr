<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace TBN\AgendaBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use TBN\MainBundle\Site\SiteManager;
use Doctrine\Common\Cache\Cache;
use TBN\SocialBundle\Social\Twitter;
use TBN\SocialBundle\Social\FacebookAdmin;
use TBN\SocialBundle\Social\Google;

/**
 * Description of AgendaExtension
 *
 * @author guillaume
 */
class AgendaExtension extends \Twig_Extension{

    public static $LIFE_TIME_CACHE = 86400; // 3600*24
    private $cache;
    private $siteManager;
    private $socials;
    private $requestStack;

    public function __construct(RequestStack $requestStack, SiteManager $siteManager, Cache $cache, FacebookAdmin $facebook, Twitter $twitter, Google $google)
    {
	$this->requestStack = $requestStack;
	$this->siteManager  = $siteManager;
	$this->cache        = $cache;
	$this->socials	    = ['facebook' => $facebook, 'twitter' => $twitter, 'google' => $google];
    }

    public function getGlobals()
    {
	$globals = [];

	$site = $this->siteManager->getCurrentSite();
	if($site !== null && $this->requestStack->getParentRequest() === null)
	{
	    foreach($this->socials as $name => $social)
	    {
		$key = 'tbn.counts.'.$name;
		if(! $this->cache->contains($key))
		{
		    $this->cache->save($key, $social->getNumberOfCount(), self::$LIFE_TIME_CACHE);
		}

		$globals['count_'.$name] = $this->cache->fetch($key);
	    }
	}

	return $globals;
    }    

    public function getName() {
        return 'agenda_extension';
    }
}
