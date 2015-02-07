<?php

namespace TBN\SocialBundle\Social;

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use TBN\UserBundle\Entity\User;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class FacebookEvents extends Facebook {
    
    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	$info = $user->getInfo();
	if ($agenda->getFbPostId() === null && $info !== null && $info->getFacebookAccessToken() !== null) {
	    
            $dateDebut  = $this->getReadableDate($agenda->getDateDebut());
            $dateFin    = $this->getReadableDate($agenda->getDateFin());
            $date       = $this->getDuree($dateDebut, $dateFin);
            
            //Authentification
            $session    = new FacebookSession($user->getInfo()->getFacebookAccessToken());
	    $request    = new FacebookRequest($session, 'POST', '/me/feed', [
		'link' => $this->getLink($agenda),
		'picture' => $this->getLinkPicture($agenda),
		'name' => $agenda->getNom(),
		'description' => $date.". ".strip_tags($agenda->getDescriptif()),
		'message' => $agenda->getNom()." @ ".$agenda->getLieuNom(),
                'privacy' => json_encode(['value' => 'SELF']),
		'actions' => json_encode([
		    [
			"name" => $user->getUsername() . " sur " . $user->getSite()->getNom() . " By Night",
			"link" => $this->getMembreLink($user)
		    ]
		])
	    ]);

            $post = $request->execute()->getGraphObject();
	    $agenda->setFbPostId($post->getProperty("id"));
	}
    }
    
    
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
