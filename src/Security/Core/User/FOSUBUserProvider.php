<?php

namespace AppBundle\Security\Core\User;

use AppBundle\App\CityManager;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider as BaseClass;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use AppBundle\Entity\UserInfo;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;

class FOSUBUserProvider extends BaseClass
{
    /**
     * @var array
     */
    private $socials;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var CityManager
     */
    private $cityManager;

    public function __construct(UserManagerInterface $userManager, array $properties, CityManager $cityManager, EntityManagerInterface $entityManager, array $socials)
    {
        parent::__construct($userManager, $properties);

        $this->cityManager   = $cityManager;
        $this->entityManager = $entityManager;
        $this->socials       = $socials;
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
     * @return \AppBundle\Social\Social
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
        $repo = $this->entityManager->getRepository('AppBundle:Info');

        $info = $repo->findOneBy([$this->getProperty($cle) => $valeur]);
        if (null !== $info) {
            return $this->entityManager->getRepository('AppBundle:User')->findOneByInfo($info);
        }

        return null;
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
        $service  = $response->getResourceOwner()->getName();

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
        if (null === $user->getInfo()) {
            $user->setInfo(new UserInfo());
        }

        if (null === $user->getCity() && $this->cityManager->getCurrentCity()) {
            $user->setCity($this->cityManager->getCurrentCity());
        }

        if (null === $user->getEmail()) {
            $user->setEmail(null === $response->getEmail() ? $response->getNickname() . '@' . $service . '.fr' : $response->getEmail());
        }

        if (null === $user->getFirstname() && null === $user->getLastname()) {
            $nom_prenoms = preg_split('/ /', $response->getRealName());
            $user->setFirstname($nom_prenoms[0]);
            if (count($nom_prenoms) > 0) {
                $user->setLastname(implode(' ', array_slice($nom_prenoms, 1)));
            }
        }

        if (null === $user->getUsername() || '' === $user->getUsername()) {
            $user->setUsername($response->getNickname());
        }

        $social = $this->getSocialService($service);
        $social->connectUser($user, $response);
    }
}
