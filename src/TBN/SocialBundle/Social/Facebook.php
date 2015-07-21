<?php

namespace TBN\SocialBundle\Social;

use TBN\SocialBundle\Exception\SocialException;

//use Facebook\FacebookSession;
use Facebook\GraphObject;
use Facebook\Facebook as Client;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class Facebook extends Social {

    /**
     *
     * @var Client $client
     */
    protected $client;

    //protected static $FIELDS = "id,name,venue,end_time,owner,cover,is_date_only,ticket_uri,description,location,picture.type(large).redirect(false)";
    protected static $FIELDS = "id,name,venue,end_time,owner,cover,is_date_only,ticket_uri,description,location";
    protected static $ATTENDING_FIELDS = "id,name,picture.type(square).redirect(false)";

    protected function constructClient() {
        $this->client = new Client([
            'app_id' => $this->id,
            'app_secret' => $this->secret,
            'default_graph_version' => 'v2.4',
        ]);
    }

    public function getPagePictureURL(GraphObject $event)
    {
	if($event->getProperty("cover"))
        {
            $cover = $event->getProperty("cover");
            return $cover->getProperty("source");
        }

	return null;
    }

    public function getNumberOfCount() { 
	throw new SocialException("Les droits de l'utilisateur sont insufisants pour récupérer des infos sur une page Facebook");
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	
        throw new SocialException("Les droits de l'utilisateur sont insufisants pour poster sur Facebook");
    }
    
    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
        throw new SocialException("Les droits du système sont insufisants pour poster sur une page Facebook");
    }

    protected function getDuree($dateDebut, $dateFin)
    {
        return $dateDebut === $dateFin ? "Le ".$dateDebut : "Du ".$dateDebut." au ".$dateFin;
    }

    protected function getReadableDate(\DateTime $date, $dateFormat = \IntlDateFormatter::FULL, $timeFormat = \IntlDateFormatter::NONE)
    {
        $intl       = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat);
        
        return $intl->format($date);
    }

    public function getName() {
	return "Facebook";
    }
}
