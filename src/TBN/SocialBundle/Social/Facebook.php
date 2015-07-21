<?php

namespace TBN\SocialBundle\Social;

use TBN\SocialBundle\Exception\SocialException;

use Facebook\FacebookSession;
use Facebook\GraphObject;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class Facebook extends Social {

    /**
     *
     * @var ApiFacebook $client
     */
    protected $client;

    protected static $FIELDS = "id,name,place,end_time,owner,cover,is_date_only,ticket_uri,description,picture.type(large).redirect(false),attending_count,maybe_count";
    //protected static $FIELDS = "id,name,place,end_time,owner,cover,is_date_only,ticket_uri,description,location";
    protected static $ATTENDING_FIELDS = "id,name,picture.type(square).redirect(false)";
    protected static $MIN_EVENT_FIELDS = "id,updated_time,owner{id}";

    protected function constructClient() {
	FacebookSession::setDefaultApplication($this->id, $this->secret);
    }

    public function getPagePictureURL(GraphObject $object, $testCover = true, $testPicture = true)
    {
	$cover = $object->getProperty("cover");
	if($testCover && $cover && $cover->getProperty("source"))
        {
            return $cover->getProperty("source");
        }

	$picture = $object->getProperty("picture");
	if($testPicture && $picture && $picture->getProperty("url") && $picture->getProperty('is_silhouette') === false)
        {
            return $picture->getProperty("url");
        }

	return null;
    }

    public function getNumberOfCount() { 
	throw new SocialException("Les droits de l'utilisateur sont insuffisants pour récupérer des informations sur une page Facebook");
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	
        throw new SocialException("Les droits de l'utilisateur sont insuffisants pour poster sur Facebook");
    }
    
    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
        throw new SocialException("Les droits du système sont insuffisants pour poster sur une page Facebook");
    }

    protected function getDuree($dateDebut, $dateFin)
    {
        return $dateDebut === $dateFin ? "Le ".$dateDebut : "Du ".$dateDebut." au ".$dateFin;
    }

    protected function getReadableDate(\DateTime $date = null, $dateFormat = \IntlDateFormatter::FULL, $timeFormat = \IntlDateFormatter::NONE)
    {
	if(! $date)
	{
	    return null;
	}
	
        $intl       = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat);
        
        return $intl->format($date);
    }

    public function getName() {
	return "Facebook";
    }
}
