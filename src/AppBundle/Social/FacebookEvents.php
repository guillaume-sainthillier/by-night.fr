<?php

namespace AppBundle\Social;

use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use AppBundle\Entity\Agenda;
use AppBundle\Entity\User;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class FacebookEvents extends FacebookListEvents
{
    protected function post(User $user, Agenda $agenda)
    {
        $info = $user->getInfo();
        if ($agenda->getFbPostId() === null && $info !== null && $info->getFacebookAccessToken() !== null) {
            $dateDebut = $this->getReadableDate($agenda->getDateDebut());
            $dateFin   = $this->getReadableDate($agenda->getDateFin());
            $date      = $this->getDuree($dateDebut, $dateFin);

            $place = $agenda->getPlace();

            //Authentification
            $request = $this->client->post('/me/feed', [
                'link'        => $this->getLink($agenda),
                'picture'     => $this->getLinkPicture($agenda),
                'name'        => $agenda->getNom(),
                'description' => $date . '. ' . strip_tags($agenda->getDescriptif()),
                'message'     => $agenda->getNom() . ($place ? ' @ ' . $place->getNom() : ''),
                //'privacy' => json_encode(['value' => 'SELF']),
                'actions' => json_encode([
                    [
                        'name' => $user->getUsername() . ' sur ' . $agenda->getPlace()->getCity()->getName() . ' By Night',
                        'link' => $this->getMembreLink($user),
                    ],
                ]),
            ], $user->getInfo()->getFacebookAccessToken());

            $post = $request->getGraphNode();
            $agenda->setFbPostId($post->getField('id'));
        }
    }

    public function connectUser(User $user, UserResponseInterface $response)
    {
        $user->addRole('ROLE_FACEBOOK_EVENTS');
        parent::connectUser($user, $response);
    }

    public function disconnectUser(User $user)
    {
        $user->removeRole('ROLE_FACEBOOK_EVENTS');
        parent::disconnectUser($user);
    }
}
