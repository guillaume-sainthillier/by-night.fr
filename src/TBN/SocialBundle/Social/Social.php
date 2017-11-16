<?php

namespace TBN\SocialBundle\Social;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * && open the template in the editor.
 */

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\App\AppManager;
use TBN\MainBundle\Picture\EventProfilePicture;
use TBN\MainBundle\Site\SiteManager;
use TBN\SocialBundle\Exception\SocialException;
use TBN\UserBundle\Entity\Info;
use TBN\UserBundle\Entity\User;

/**
 * Description of Twitter.
 *
 * @author guillaume
 */
abstract class Social
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var SiteManager
     */
    protected $siteManager;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventProfilePicture
     */
    protected $eventProfilePicture;

    /**
     * @var AppManager
     */
    protected $appManager;

    protected $isInitialized;

    public function __construct($config, SiteManager $siteManager, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, AppManager $appManager)
    {
        if (!isset($config['id'])) {
            throw new SocialException("Le paramètre 'id' est absent");
        }

        if (!isset($config['secret'])) {
            throw new SocialException("Le paramètre 'secret' est absent");
        }

        $this->id                  = $config['id'];
        $this->secret              = $config['secret'];
        $this->config              = $config;
        $this->siteManager         = $siteManager;
        $this->tokenStorage        = $tokenStorage;
        $this->router              = $router;
        $this->session             = $session;
        $this->requestStack        = $requestStack;
        $this->logger              = $logger;
        $this->eventProfilePicture = $eventProfilePicture;
        $this->appManager          = $appManager;
        $this->isInitialized       = false;
    }

    protected function init()
    {
        if (!$this->isInitialized) {
            $this->constructClient();
            $this->isInitialized = true;
        }
    }

    public function disconnectUser(User $user)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)

        $user->removeRole('ROLE_'.strtolower($social_name)); //Suppression du role ROLE_TWITTER
        $this->disconnectInfo($user->getInfo());
    }

    protected function disconnectInfo(Info $info)
    {
        if (null !== $info) {
            $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)
            $methods     = ['Id', 'AccessToken', 'RefreshToken', 'TokenSecret', 'Nickname', 'RealName', 'Email', 'ProfilePicture'];
            foreach ($methods as $methode) {
                $setter = 'set'.ucfirst($social_name).ucfirst($methode);
                $info->$setter(null);
            }
        }
    }

    public function disconnectSite()
    {
        $this->disconnectInfo($this->siteManager->getSiteInfo());
    }

    protected function connectInfo(Info $info, UserResponseInterface $response)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)
        if (null !== $info) {
            $methods = ['AccessToken', 'RefreshToken', 'TokenSecret', 'ExpiresIn', 'Nickname', 'RealName', 'Email', 'ProfilePicture'];
            foreach ($methods as $methode) {
                $setter = 'set'.ucfirst($social_name).ucfirst($methode); // setSocialUsername
                $getter = 'get'.ucfirst($methode); //getSocialUsername

                $info->$setter($response->$getter());
            }

            $setter_id = 'set'.ucfirst($social_name).'Id';
            $info->$setter_id($response->getUsername());
        }
    }

    public function connectUser(User $user, UserResponseInterface $response)
    {
        $social_name = $this->getName(); //On récupère le nom du child (Twitter, Google, Facebook)

        $user->addRole('ROLE_'.strtolower($social_name)); //Ajout du role ROLE_TWITTER
        $this->connectInfo($user->getInfo(), $response);
    }

    public function connectSite(UserResponseInterface $response)
    {
        $this->connectInfo($this->siteManager->getSiteInfo(), $response);
    }

    public function poster(Agenda $agenda)
    {
        $this->init();
        $user = $this->tokenStorage->getToken()->getUser();

        try {
            $this->post($user, $agenda);
            $this->afterPost($user, $agenda);
        } catch (\Exception $ex) {
            $type = 'error';
            if ($ex instanceof SocialException) {
                $type = $ex->getType();
            }

            $this->session->getFlashBag()->add(
                $type,
                sprintf('Une erreur est survenue sur <b>%s</b> : %s', $this->getName(), $ex->getMessage())
            );
        }
    }

    protected function getLinkPicture(Agenda $agenda)
    {
        return $this->eventProfilePicture->getOriginalPictureUrl($agenda);
    }

    protected function getLink(Agenda $agenda)
    {
        return $this->router->generate('tbn_agenda_details', ['slug' => $agenda->getSlug(), 'id' => $agenda->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    protected function getMembreLink(User $user)
    {
        return $this->router->generate('tbn_user_details', ['id' => $user->getId(), 'slug' => $user->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    abstract public function getNumberOfCount();

    abstract protected function constructClient();

    abstract protected function getName();

    /**
     * @param User   $user
     * @param Agenda $agenda La soirée concernée
     *
     * @throws SocialException si une erreur est survenue
     */
    abstract protected function post(User $user, Agenda $agenda);

    abstract protected function afterPost(User $user, Agenda $agenda);
}
