<?php

namespace TBN\SocialBundle\Social;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\UserBundle\Entity\SiteInfo;

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphPage;
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

    //protected static $FIELDS = "id,name,venue,end_time,owner,cover,is_date_only,ticket_uri,description,location,picture.type(large).redirect(false)";
    protected static $FIELDS = "id,name,venue,end_time,owner,cover,is_date_only,ticket_uri,description,location";
    protected static $ATTENDING_FIELDS = "id,name,picture.type(square).redirect(false)";

    protected function constructClient() {
	FacebookSession::setDefaultApplication($this->id, $this->secret);
    }

    public function getPagePicture(GraphObject $event)
    {
	if($event->getProperty("cover"))
        {
            $cover = $event->getProperty("cover");
            return $cover->getProperty("source");
        }

	return null;
    }

    public function getNumberOfCount() { 
	return 0;
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	$info = $user->getInfo();
	if ($agenda->getFbPostId() == null and $info !== null and $info->getFacebookAccessToken() !== null) {
	    
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
                //'privacy' => json_encode(['value' => 'SELF']),
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

    protected function getDuree($dateDebut, $dateFin)
    {
        return $dateDebut === $dateFin ? "Le ".$dateDebut : "Du ".$dateDebut." au ".$dateFin;
    }

    protected function getReadableDate(\DateTime $date, $dateFormat = \IntlDateFormatter::FULL, $timeFormat = \IntlDateFormatter::NONE)
    {
        $intl       = new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat);
        
        return $intl->format($date);
    }

    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
        $info = $this->siteManager->getSiteInfo();
	if ($agenda->getFbPostSystemId() == null and $info !== null and $info->getFacebookAccessToken() !== null)
        {
            $site       = $this->siteManager->getCurrentSite();
            $dateDebut  = $this->getReadableDate($agenda->getDateDebut());
            $dateFin    = $this->getReadableDate($agenda->getDateFin());
            $date       = $this->getDuree($dateDebut, $dateFin);
            $message    = $user->getUsername() . " présente\n". $agenda->getNom()." @ ".$agenda->getLieuNom();

            //Authentification
	    $session = new FacebookSession($info->getFacebookAccessToken());	    

	    $request = new FacebookRequest($session, 'POST', '/' . $site->getFacebookIdPage() . '/feed', [
		'message' => $message,
		'name' => $agenda->getNom(),
                'link' => $this->getLink($agenda),
		'picture' => $this->getLinkPicture($agenda),
                'description' => $date.". ".strip_tags($agenda->getDescriptif()),
		'actions' => json_encode([
                    [
                        "name" => $user->getUsername() . " sur " . $user->getSite()->getNom() . " By Night",
                        "link" => $this->getMembreLink($user)
                    ]
                ])
	    ]);

	    $post = $request->execute()->getGraphObject();

	    $agenda->setFbPostSystemId($post->getProperty("id"));
	}
    }

    public function getName() {
	return "Facebook";
    }
}
