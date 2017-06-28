<?php

namespace TBN\MainBundle\App;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 21:57.
 */
class AppManager
{
    private $facebookIdPage;

    public function __construct(ContainerInterface $container)
    {
        $this->facebookIdPage = $container->getParameter('facebook_id_page');
        $this->twitterIdPage  = $container->getParameter('twitter_id_page');
        $this->googleIdPage   = $container->getParameter('google_id_page');
    }

    public function getFacebookIdPage()
    {
        return $this->facebookIdPage;
    }

    public function getTwitterIdPage()
    {
        return $this->twitterIdPage;
    }

    public function getGoogleIdPage()
    {
        return $this->googleIdPage;
    }
}
