<?php

namespace TBN\UserBundle\Security\Core\User;

use FOS\UserBundle\Model\UserManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\Security\Core\User\UserInterface;
use TBN\MainBundle\Site\SiteManager;
use TBN\UserBundle\Entity\UserInfo;

class FOSUBUserProvider extends BaseClass
{
    private $socials;
    private $doctrine;
    private $siteManager;

    public function __construct(UserManagerInterface $userManager, array $properties, SiteManager $siteManager, $doctrine, array $socials)
    {
        parent::__construct($userManager, $properties);

        $this->siteManager = $siteManager;
        $this->doctrine = $doctrine;
        $this->socials = $socials;
    }

    public function connectSite(UserResponseInterface $response)
    {
        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName(); //google, facebook,...

        $social = $this->getSocialService($service);
        $social->connectSite($response);
    }

    /**
     * {@inheritdoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $username = $response->getUsername(); //ID de l'user sur le réseau social

        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName(); //google, facebook,...

        //On récupère le service gérant les infos
        $social = $this->getSocialService($service);

        $previousUser = $this->findUserBySocialInfo($response, $username);
        if (null !== $previousUser) {
            $social->disconnectUser($previousUser);
            $this->userManager->updateUser($previousUser);
        }

        $this->hydrateUser($user, $response, $service);
        $this->userManager->updateUser($user);
    }

    /**
     * @param string $service
     *
     * @return \TBN\SocialBundle\Social\Social
     */
    protected function getSocialService($service)
    {
        $key = strtolower($service);
        if (!isset($this->socials[$key])) {
            throw new RuntimeException(sprintf('Le service %s est introuvable', $service));
        }

        return $this->socials[$key];
    }

    protected function findUserBySocialInfo(UserResponseInterface $cle, $valeur)
    {
        $em = $this->doctrine->getManager();
        $repo = $em->getRepository('TBNUserBundle:Info');

        $info = $repo->findOneBy([$this->getProperty($cle) => $valeur]);
        if ($info !== null) {
            return $em->getRepository('TBNUserBundle:User')->findOneByInfo($info);
        }
    }

    protected function getProperty(UserResponseInterface $response)
    {
        if (preg_match('/facebook/i', $response->getResourceOwner()->getName())) {
            $response->getResourceOwner()->setName('facebook');
        }

        return parent::getProperty($response);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $username = $response->getUsername();
        $service = $response->getResourceOwner()->getName();

        // Recherche de l'user par son id sur les réseaux sociaux (facebook_id)
        $user = $this->findUserBySocialInfo($response, $username);

        //Recherche de l'user par l'email du compte social associé
        if (null === $user) {
            $user = $this->userManager->findUserBy(['email' => $response->getEmail()]);
        }

        //Si l'utilisateur n'existe pas on le créé
        if (null === $user) {
            //
            // Création de l'utilisateur
            $user = $this->userManager->createUser();

            //Affectation des données primaires
            $user->setPassword($username); //Obligatoire
            $user->setEnabled(true);

            //On définit le profil de l'utilisateur
            $this->hydrateUser($user, $response, $service);
            $this->userManager->updateUser($user); // Mise à jour

            return $user;
        }

        if (null === $user) {
            //if user exists - go with the HWIOAuth way
            $user = parent::loadUserByOAuthUserResponse($response);
        }

        //On met à jour l'utilisateur
        $this->hydrateUser($user, $response, $service);
        $this->userManager->updateUser($user); // Mise à jour

        return $user;
    }

    protected function hydrateUser(UserInterface $user, UserResponseInterface $response, $service)
    {
        if ($user->getInfo() === null) {
            $user->setInfo(new UserInfo());
        }

        if ($user->getSite() === null && $this->siteManager->getCurrentSite()) {
            $user->setSite($this->siteManager->getCurrentSite());
        }

        if ($user->getEmail() === null) {
            $user->setEmail($response->getEmail() === null ? $response->getNickname().'@'.$service.'.fr' : $response->getEmail());
        }

        if ($user->getFirstname() === null && $user->getLastname() === null) {
            $nom_prenoms = preg_split('/ /', $response->getRealName());
            $user->setFirstname($nom_prenoms[0]);
            if (count($nom_prenoms) > 0) {
                $user->setLastname(implode(' ', array_slice($nom_prenoms, 1)));
            }
        }

        if ($user->getUsername() === null || $user->getUsername() === '') {
            $user->setUsername($response->getNickname());
        }

        $social = $this->getSocialService($service);
        $social->connectUser($user, $response);
    }
}
