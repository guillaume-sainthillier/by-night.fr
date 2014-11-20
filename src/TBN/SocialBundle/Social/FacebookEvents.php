<?php

namespace TBN\SocialBundle\Social;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\UserBundle\Entity\SiteInfo;

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphPage;
use Facebook\GraphObject;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use TBN\UserBundle\Entity\User;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class FacebookEvents extends Facebook {
    
   
    public function connectUser(User $user, UserResponseInterface $response)
    {
        $user->addRole("ROLE_FACEBOOK_EVENTS");
        parent::connectUser($user, $response);
    }

    public function disconnectUser(User $user)
    {
        $user->removeRole("ROLE_FACEBOOK_EVENTS");
        parent::disconnectUser($user);
    }
}
