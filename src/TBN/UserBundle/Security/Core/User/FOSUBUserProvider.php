<?php
namespace TBN\UserBundle\Security\Core\User;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use TBN\MainBundle\Entity\Site;
use TBN\UserBundle\Entity\Info;
use TBN\SocialBundle\Social\Social;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;

class FOSUBUserProvider extends BaseClass
{
    /** @var ContainerInterface */
    protected $container;

    public function __construct(\FOS\UserBundle\Model\UserManagerInterface $userManager, ContainerInterface $container, array $properties) {
        $this->container = $container;
        parent::__construct($userManager, $properties);
    }

    public function connectSite(UserResponseInterface $response)
    {
        //$username = $response->getUsername(); //ID de l'user sur le réseau social

        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();//google, facebook,...

        $social = $this->getSocialService($service);
        $social->connectSite($response);
    }

    /**
     * {@inheritDoc}
     */
    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        //$property = $this->getProperty($response); //champs pour récupérer l'user, ici username
        $username = $response->getUsername(); //ID de l'user sur le réseau social

        //on connect - get the access token and the user ID
        $service = $response->getResourceOwner()->getName();//google, facebook,...

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
     *
     * @param string $service
     * @return \TBN\SocialBundle\Social\Social
     */
    protected function getSocialService($service)
    {
        $social = $this->container->get("tbn.social.".strtolower($service));
        if(! $social instanceof Social)
        {
            throw new RuntimeException(sprintf("Le service %s est introuvable", $service));
        }

        return $social;
    }

    protected function findUserBySocialInfo(UserResponseInterface $cle, $valeur)
    {
        $doctrine   = $this->container->get("doctrine");
        $em         = $doctrine->getManager();
        $repo       = $em->getRepository('TBNUserBundle:Info');

        //TODO: faire une seule requête
        $info       = $repo->findOneBy([$this->getProperty($cle) => $valeur]);
        if($info !== null)
        {
            return $em->getRepository('TBNUserBundle:User')->findOneByInfo($info);
        }

        return null;
    }

    protected function getProperty(UserResponseInterface $response)
    {
        if(preg_match("/facebook/i", $response->getResourceOwner()->getName()))
        {
            $response->getResourceOwner()->setName("facebook");
        }

        return parent::getProperty($response);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {
        $username 	= $response->getUsername();
        $service        = $response->getResourceOwner()->getName();


        // Recherche de l'user par son id sur les réseaux sociaux (facebook_id)
        $user = $this->findUserBySocialInfo($response, $username);

        //Recherche de l'user par l'email du compte social associé
        if(null === $user){
            $user = $this->userManager->findUserBy(["email" => $response->getEmail()]);
        }

        //Si l'utilisateur n'existe pas on le créé
        if (null === $user){
            //
            // Création de l'utilisateur
            $user = $this->userManager->createUser();

            //Affectation des données primaires
            $user->setPassword($username); //Obligatoire
            $user->setEnabled(true);

            //On définit le profil de l'utilisateur
            $this->hydrateUser($user,$response,$service);
            $this->userManager->updateUser($user);// Mise à jour

            return $user;
        }

        if(null === $user)
        {
            //if user exists - go with the HWIOAuth way
            $user = parent::loadUserByOAuthUserResponse($response);
        }

        //On met à jour l'utilisateur
        $this->hydrateUser($user, $response, $service);        
        $this->userManager->updateUser($user);// Mise à jour

        return $user;
    }

    protected function hydrateUser(UserInterface $user, UserResponseInterface $response, $service)
    {
        if($user->getInfo() === null)
        {
            $user->setInfo(new Info);
        }

        if($user->getSite() === null)
        {
            $villeListener = $this->container->get("ville_listener");
            $siteManager = $villeListener->getSiteManager();
            $user->setSite($siteManager->getCurrentSite());
        }

        if($user->getEmail() === null)
        {
            $user->setEmail($response->getEmail() === null ? $response->getNickname()."@".$service.".fr" : $response->getEmail());
        }

        if($user->getFirstname() === null && $user->getLastname() === null)
        {
            $nom_prenoms = preg_split("/ /",$response->getRealname());
            $user->setFirstname($nom_prenoms[0]);
            if(count($nom_prenoms) > 0)
            {
                $user->setLastname(implode(" ",array_slice($nom_prenoms, 1)));
            }
        }

        if($user->getUsername() === null || $user->getUsername() === "")
        {
            $user->setUsername($response->getNickname());
        }

        $social = $this->getSocialService($service);
        $social->connectUser($user, $response);
    }
}
