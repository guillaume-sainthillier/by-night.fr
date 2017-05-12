<?php

namespace AppBundle\App;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 14/12/2016
 * Time: 21:57
 */
class AppManager
{
    /**
     * @var string
     */
    private $facebookIdPage;

    /**
     * @var string
     */
    private $twitterIdPage;

    /**
     * @var string
     */
    private $googleIdPage;

    public function __construct(ContainerInterface $container)
    {
        $this->facebookIdPage = $container->getParameter('facebook_id_page');
        $this->twitterIdPage = $container->getParameter('twitter_id_page');
        $this->googleIdPage = $container->getParameter('google_id_page');
    }

    /**
     * @return string
     */
    public function getFacebookIdPage()
    {
        return $this->facebookIdPage;
    }

    /**
     * @return string
     */
    public function getTwitterIdPage()
    {
        return $this->twitterIdPage;
    }

    /**
     * @return string
     */
    public function getGoogleIdPage()
    {
        return $this->googleIdPage;
    }
}
