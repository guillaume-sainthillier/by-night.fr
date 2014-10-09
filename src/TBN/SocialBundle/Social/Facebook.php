<?php

namespace TBN\SocialBundle\Social;

use TBN\UserBundle\Entity\Info;
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

    protected static $FIELDS = "id,name,venue,end_time,owner,cover,is_date_only,ticket_uri,description,location,picture.type(large).redirect(false)";

    protected function constructClient() {
	FacebookSession::setDefaultApplication($this->id, $this->secret);
    }

    public function getEventStats(Info $info, $id_event)
    {
	$session	= new FacebookSession($info->getFacebookAccessToken());

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event."/attending");
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
	$partipations	= count($graph->getPropertyAsArray("data"));

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event."/maybe");
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
	$interets	= count($graph->getPropertyAsArray("data"));

	return [
	    "participations"	=> $partipations,
	    "interets"		=> $interets
	];
    }

    public function getPagePicture(GraphObject $event)
    {
	if($event->getProperty("cover"))
        {
            $cover = $event->getProperty("cover");
            return $cover->getProperty("source");
        }elseif($event->getProperty("picture"))
        {
	    $picture = $event->getProperty("picture");
	    return $picture->getProperty("url");
        }

	return null;
    }

    public function getPageFromId(Info $info, $id_page)
    {
	$session = new FacebookSession($info->getFacebookAccessToken());
	$request = new FacebookRequest($session, 'GET', '/' .$id_page);

	return $request->execute()->getGraphObject(GraphPage::className());
    }

    public function searchEventsFromOwnerIds($ids, Info $info, \DateTime $since, $limit = 5000) {

	if(count($ids) === 0)
	{
	    return [];
	}

	$session = new FacebookSession($info->getFacebookAccessToken());
	$request = new FacebookRequest($session, 'GET', '/events', [
	    "since"	=> $since->format("Y-m-d"),
	    "ids"	=> implode(",",$ids),
	    "fields"	=> Facebook::$FIELDS,
	    "limit"	=> $limit
	]);

	$graph	    = $request->execute()->getGraphObject();
	return $graph;
    }

    public function searchEventsFromKeywords($keywords, Info $info, \DateTime $since, $limit = 5000) {
	$offset	    = 0;
	$session    = new FacebookSession($info->getFacebookAccessToken());
	$events	    = [];

	//Récupération des events en fonction d'un mot-clé
	foreach($keywords as $keyword)
	{
	    $request	= new FacebookRequest($session, 'GET', '/search', [
		"q"	    => $keyword,
		"type"	    => "event",
		"since"	    => $since->format("Y-m-d"),
		"fields"    => Facebook::$FIELDS,
		"offset"    => $offset,
		"limit"	    => $limit
	    ]);

	    $graph	= $request->execute()->getGraphObject();
	    $data	= $graph->getPropertyAsArray("data");
	    $events	+= $data;
	}

	return $events;
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
	if ($user->hasRole("ROLE_FACEBOOK") and $agenda->getFbPostId() == null and $info !== null and $info->getFacebookAccessToken() !== null) {
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
	if ($user->hasRole("ROLE_FACEBOOK") and $agenda->getFbPostSystemId() == null) {
	    $site = $this->siteManager->getCurrentSite();

	    $session = new FacebookSession($this->siteManager->getSiteInfo()->getFacebookAccessToken());

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
