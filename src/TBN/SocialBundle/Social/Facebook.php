<?php

namespace TBN\SocialBundle\Social;

use TBN\SocialBundle\Exception\SocialException;

use Facebook\GraphNodes\GraphNode;
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
    //protected static $FIELDS = "id,name,place,end_time,owner,cover,is_date_only,ticket_uri,description";
    protected static $FIELDS            = "id,name,updated_time,place,start_time,end_time,owner{category,website,phone,picture.type(large).redirect(false)},cover,ticket_uri,description,picture.type(large).redirect(false),attending_count,maybe_count";
    protected static $STATS_FIELDS      = "id,picture.type(large).redirect(false),cover,attending_count,maybe_count";
    protected static $FULL_STATS_FIELDS = "id,picture.type(large).redirect(false),cover,attending_count,maybe_count,attending.limit(500){name,picture.type(square).redirect(false)},maybe.limit(500){name,picture.type(square).redirect(false)}";
    protected static $ATTENDING_FIELDS  = "id,name,picture.type(square).redirect(false)";
    protected static $MIN_EVENT_FIELDS  = "id,updated_time,owner{id},place{id}";

    protected function constructClient() {
        $this->client = new Client([
            'app_id' => $this->id,
            'app_secret' => $this->secret,
            'default_graph_version' => 'v2.6',
        ]);
    }

    public function ensureGoodValue($value)
    {
        return $value !== '<<not-applicable>>' ? $value : null;
    }

    public function getPagePictureURL(GraphNode $object, $testCover = true, $testPicture = true)
    {
        $cover = $object->getField("cover");
        if($testCover && $cover && $cover->getField("source"))
        {
            return $this->ensureGoodValue($cover->getField("source"));
        }
        
        $picture = $object->getField("picture");
        if($testPicture && $picture && $picture->getField("url") && $picture->getField('is_silhouette') === false)
        {
            return $this->ensureGoodValue($picture->getField("url"));
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
