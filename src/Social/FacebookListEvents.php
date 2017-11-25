<?php

namespace AppBundle\Social;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use AppBundle\Entity\User;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class FacebookListEvents extends Facebook
{
    public function getUserEvents(User $user, $limit = 5000)
    {
        $this->init();
        $userInfo = $user->getInfo();

        if (!$userInfo || !$userInfo->getFacebookAccessToken()) {
            throw new \RuntimeException(\sprintf(
                "Unable to find user social infos for user '%d'",
                $user->getId()
            ));
        }

        $this->client->setDefaultAccessToken($userInfo->getFacebookAccessToken());

        $request = $this->client->sendRequest('GET', '/' . $userInfo->getFacebookId() . '/events', [
            'type'   => 'created',
            'fields' => self::FIELDS,
            'limit'  => $limit,
        ]);

        return $this->findPaginated($request->getGraphEdge());
    }

    public function connectUser(User $user, UserResponseInterface $response)
    {
        $user->addRole('ROLE_FACEBOOK_LIST_EVENTS');
        parent::connectUser($user, $response);
    }

    public function disconnectUser(User $user)
    {
        $user->removeRole('ROLE_FACEBOOK_LIST_EVENTS');
        parent::disconnectUser($user);
    }
}
