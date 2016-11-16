<?php

namespace TBN\SocialBundle\Social;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use TBN\UserBundle\Entity\User;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class FacebookListEvents extends Facebook
{

    public function getUserEvents(User $user, $limit = 5000) {
        $userInfo = $user->getInfo();

        if(! $userInfo) {
            throw new \RuntimeException("Unable to find user social infos");
        }

        $this->client->setDefaultAccessToken($userInfo->getFacebookAccessToken());

        $request = $this->client->sendRequest('GET', '/' . $userInfo->getFacebookId(). '/events', [
            'type' => "created",
            'fields' => self::$FIELDS,
            'limit' => $limit
        ]);

        return $this->findPaginated($request->getGraphEdge());
    }

    public function connectUser(User $user, UserResponseInterface $response)
    {
        $user->addRole("ROLE_FACEBOOK_LIST_EVENTS");
        parent::connectUser($user, $response);
    }

    public function disconnectUser(User $user)
    {
        $user->removeRole("ROLE_FACEBOOK_LIST_EVENTS");
        parent::disconnectUser($user);
    }
}
