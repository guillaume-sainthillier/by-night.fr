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
//        elseif($event->getProperty("picture"))
//        {
//	    $picture = $event->getProperty("picture");
//	    return $picture->getProperty("url");
//        }

	return null;
    }

    public function getPageFromId(SiteInfo $info, $id_page)
    {
	$session = new FacebookSession($info->getFacebookAccessToken());
	$request = new FacebookRequest($session, 'GET', '/' .$id_page);

	return $request->execute()->getGraphObject(GraphPage::className());
    }

    public function getEventsFromUsers($users, SiteInfo $info, \DateTime $since, OutputInterface $output, $limit = 5000) {
        $events             = [];
        $usersPerRequest    = 50;
        $totalUsers         = count($users);
        $iterations         = ceil($totalUsers / $usersPerRequest);

        for($i = 0; $i < $iterations; $i++)
        {
            $currentUsers   = array_slice($users, $i*$usersPerRequest, $usersPerRequest);

            $session        = new FacebookSession($info->getFacebookAccessToken());
            $request        = new FacebookRequest($session, 'GET', '/events', [
                "since"     => $since->format("Y-m-d"),
                "ids"       => implode(",", $currentUsers),
                "fields"    => self::$FIELDS,
                "limit"     => $limit
            ]);

            $graph	    = $request->execute()->getGraphObject();
            if ($graph->getProperty("error_code")) {
                $output->writeln(\utf8_decode(sprintf("<error>Erreur #%d : %s</error>", $graph->getProperty("error_code"), $graph->getProperty("error_msg"))));
            }else
            {
                $real_owner_ids     = $graph->getPropertyNames();
                foreach ($real_owner_ids as $id)
                {
                    $owner_events   = $graph->getProperty($id);
                    $events         = array_merge($events, $owner_events->getPropertyAsArray("data"));
                }
            }
        }
	
	return $events;
    }

    public function getPlacesFromGPS(SiteInfo $info, $latitude, $longitude, $distance, $limit = 5000)
    {
        $session        = new FacebookSession($info->getFacebookAccessToken());
        $request	= new FacebookRequest($session, 'GET', '/search', [
            "q"             => "*",
            "type"	    => "place",
            "center"        => $latitude.",".$longitude,
            "distance"      => $distance,
            "fields"        => "name",
            "limit"	    => $limit
        ]);

        $graph	= $request->execute()->getGraphObject();
        $data	= $graph->getPropertyAsArray("data");

        return array_map(function(GraphObject $place)
        {
            return $place->getProperty("name");
        }, $data);
    }

    public function searchEventsFromKeywords($keywords, SiteInfo $info, \DateTime $since,OutputInterface $output, $limit = 5000) {
	$session    = new FacebookSession($info->getFacebookAccessToken());
	$events	    = [];

	//Récupération des events en fonction d'un mot-clé
	foreach($keywords as $keyword)
	{
            try {
                $request	= new FacebookRequest($session, 'GET', '/search', [
                    "q"	    => $keyword,
                    "type"	    => "event",
                    "since"	    => $since->format("Y-m-d"),
                    "fields"    => self::$FIELDS,
                    "limit"	    => $limit
                ]);

                $graph	= $request->execute()->getGraphObject();
                $data	= $graph->getPropertyAsArray("data");
                $events	+= $data;
            } catch(\Exception $e)
            {
                $output->writeln("<error>Erreur dans la recherche par mot-clé : ".$e->getMessage()."</error>");
                sleep(600);
            }
	}

	return $events;
    }

    public function getEventStats(SiteInfo $info, $id_event)
    {
	$session	= new FacebookSession($info->getFacebookAccessToken());

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event."/attending", [
            "fields" => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
	$partipations	= $graph->getPropertyAsArray("data");

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event."/maybe", [
            "fields" => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
	$interets	= $graph->getPropertyAsArray("data");

	return [
	    "participations"	=> count($partipations),
	    "interets"		=> count($interets),
            "membres"           => $partipations + $interets
	];
    }

    public function getNumberOfCount() {

	$site   = $this->siteManager->getCurrentSite();
        $info   = $this->siteManager->getSiteInfo();

	if ($site !== null and $info !== null) {
	    try {
		$page = $this->getPageFromId($info, $site->getFacebookIdPage());

		return $page->getProperty("likes");
	    } catch (\Exception $ex) {
		// TODO : Logger
	    }
	}

	return 0;
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	$info = $user->getInfo();
	if ($agenda->getFbPostId() == null and $info !== null and $info->getFacebookAccessToken() !== null) {
	    //Authentification
	    $session = new FacebookSession($user->getInfo()->getFacebookAccessToken());
	    $request = new FacebookRequest($session, 'POST', '/me/feed', [
		'link' => $this->getLink($agenda),
		'picture' => $this->getLinkPicture($agenda),
		'name' => $agenda->getNom(),
		'description' => $agenda->getDescriptif(),
		'message' => $agenda->getDescriptif(),
		'actions' => [
		    [
			"name" => $user->getUsername() . " sur " . $user->getSite()->getNom() . " By Night",
			"link" => $this->getMembreLink($user)
		    ]
		],
		'privacy' => ["value" => "SELF"]
	    ]);

	    $post = $request->execute()->getGraphObject();

	    $agenda->setFbPostId($post->getProperty("id"));
	}
    }

    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
        $info = $this->siteManager->getSiteInfo();
	if ($agenda->getFbPostSystemId() == null and $info !== null and $info->getFacebookAccessToken() !== null)
        {
            $site = $this->siteManager->getCurrentSite();
	    $session = new FacebookSession($info->getFacebookAccessToken());

	    $message = $user->getUsername() . " présente :"
		    . "\n"
		    . $agenda->getNom()
		    . "\n\n Tous les détails sur " . $this->getLink($agenda);

	    $request = new FacebookRequest($session, 'POST', '/' . $site->getFacebookIdPage() . '/feed', [
		'message' => $message,
		'name' => $agenda->getNom(),
		'actions' => [
		    [
			"name" => $user->getUsername() . " sur " . $user->getSite()->getNom() . " By Night",
			"link" => $this->getMembreLink($user)
		    ]]
	    ]);

	    $post = $request->execute()->getGraphObject();
	    $agenda->setFbPostSystemId($post->getProperty("id"));
	}
    }

    public function getName() {
	return "Facebook";
    }
}
