<?php

namespace TBN\SocialBundle\Social;

use Doctrine\Common\Persistence\ObjectManager;

use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphPage;
<<<<<<< HEAD
use Facebook\GraphObject;
use Facebook\FacebookSDKException;
=======
use Facebook\GraphNodes\GraphNode;
>>>>>>> MAJ

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class FacebookAdmin extends FacebookEvents {

     /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

     /**
     *
     * @var AgendaParser
     */
    protected $parser;

    protected $cache;

    public function __construct($config, \TBN\MainBundle\Site\SiteManager $siteManager, \Symfony\Component\Security\Core\SecurityContextInterface $securityContext, \Symfony\Component\Routing\RouterInterface $router, \Symfony\Component\HttpFoundation\Session\SessionInterface $session, \Symfony\Component\HttpFoundation\RequestStack $requestStack, ObjectManager $om) {
        parent::__construct($config, $siteManager, $securityContext, $router, $session, $requestStack);

        $this->siteInfo = $this->siteManager->getSiteInfo();
	$this->cache	= [];
        $this->parser	= null;

	//CLI
	if(!$this->siteInfo)
	{
	    $this->siteInfo = $om->getRepository('TBNUserBundle:SiteInfo')->findOneBy([]);
	}
    }
    
    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {
	if ($agenda->getFbPostSystemId() === null && $this->siteInfo !== null && $this->siteInfo->getFacebookAccessToken() !== null)
        {
            $site       = $this->siteManager->getCurrentSite();
            $dateDebut  = $this->getReadableDate($agenda->getDateDebut());
            $dateFin    = $this->getReadableDate($agenda->getDateFin());
            $date       = $this->getDuree($dateDebut, $dateFin);
            $message    = $user->getUsername() . ' présente\n'. $agenda->getNom().' @ '.$agenda->getLieuNom();

            //Authentification
            $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	    $request = $this->client->post('/' . $site->getFacebookIdPage() . '/feed', [
		'message' => $message,
		'name' => $agenda->getNom(),
                'link' => $this->getLink($agenda),
		'picture' => $this->getLinkPicture($agenda),
                'description' => $date.'. '.strip_tags($agenda->getDescriptif()),
		'actions' => json_encode([
                    [
                        'name' => $user->getUsername() . ' sur ' . $user->getSite()->getNom() . ' By Night',
                        'link' => $this->getMembreLink($user)
                    ]
                ])
	    ]);

	    $post = $request->getGraphObject();

	    $agenda->setFbPostSystemId($post->getField('id'));
	}
    }

    public function getNumberOfCount() {
	$site   = $this->siteManager->getCurrentSite();

	if ($site !== null && $this->siteInfo !== null) {
	    try {
		$page = $this->getPageFromId($site->getFacebookIdPage());                
<<<<<<< HEAD
		return $page->getProperty('likes');
	    } catch (\Exception $ex) {}
=======
		return $page->getField('likes');
	    } catch (\Exception $ex) {
	    }
>>>>>>> MAJ
	}

	return 0;
    }

    public function getEventFromId($id_event)
    {
	$key = 'events'.$id_event;
	if(! isset($this->cache[$key]))
	{
	    $session = new FacebookSession($this->siteInfo->getFacebookAccessToken());
	    $request = new FacebookRequest($session, 'GET', '/' .$id_event, [
		'fields' => self::$FIELDS
	    ]);
	    
	    $this->cache[$key] = $request->execute()->getGraphObject();
	}

	return $this->cache[$key];
    }

    public function getEventsFromIds($ids_event)
    {
	$events = [];

	try {
	    $session		= new FacebookSession($this->siteInfo->getFacebookAccessToken());
	    $request		= new FacebookRequest($session, 'GET', '/', [
		'ids'	    => implode(',', $ids_event),
		'fields'    => self::$FIELDS
	    ]);

	    $fbEvents	= $request->execute()->getGraphObject();
	    $id_events	= $fbEvents->getPropertyNames();
	    foreach($id_events as $id_event)
	    {
		$events[] = $fbEvents->getProperty($id_event);
	    }
	} catch (FacebookSDKException $ex) {
	    $this->parser->writeln('<error>Erreur dans la récupération des pages : '. $ex->getMessage() .'</error>');

	    foreach($ids_event as $id_event)
	    {
		try {
		    $events[] = $this->getEventFromId($id_event);
		} catch (FacebookSDKException $ex) {
		    $this->parser->writeln(sprintf(
			    '<error>Erreur dans la récupération de l\'événement #%s : %s</error>', $id_event, $ex->getMessage()
		    ));
		}
	    }
	}

	return $events;
    }

    public function getPageFromId($id_page, $params = [])
    {
	$key = 'pages.'.$id_page;
	if(! isset($this->cache[$key]))
	{
	    $session = new FacebookSession($this->siteInfo->getFacebookAccessToken());
	    $request = new FacebookRequest($session, 'GET', '/' .$id_page, $params);

	    $this->cache[$key] = $request->execute()->getGraphObject(GraphPage::className());
	}

	return $this->cache[$key];
    }
    
<<<<<<< HEAD
    public function searchEvents($keywords, \DateTime $since, $limit = 500) {
	$session    = new FacebookSession($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	//$keywords   = array_slice($keywords, 0, 50);
=======
    public function searchEventsFromKeywords($keywords, \DateTime $since, $limit = 50) {
        
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	$keywords   = array_slice($keywords, 0, 5);
>>>>>>> MAJ

	$nbKeywords = count($keywords);
	//Récupération des events en fonction d'un mot-clé
	foreach($keywords as $i => $keyword)
	{
            try {
		$start = microtime(true);
                $request	= $this->client->sendRequest('GET', '/search', [
                    'q'		    => $keyword,
                    'type'	    => 'event',
                    'since'	    => $since->format('Y-m-d'),
                    'fields'	    => self::$MIN_EVENT_FIELDS,
                    'limit'	    => $limit
                ]);

<<<<<<< HEAD
                $datas	= $request->execute()->getGraphObjectList();
                $events	= array_merge($events, $datas);
=======
                $graph	= $request->getGraphEdge();
                $data	= $graph->getIterator();
                $events	= array_merge($events, $data->getArrayCopy());
>>>>>>> MAJ
		$end	= microtime(true);
		$this->parser->writeln(sprintf('%d / %d: <info>%d</info> événement(s) trouvé(s) pour %s en <info>%d ms</info>',
			$i, $nbKeywords - 1, count($datas), $keyword, ($end - $start)* 1000));
            }catch(FacebookSDKException $e)
            {
                $this->parser->writeln('<error>Erreur dans la recherche par mot-clé : '.$e->getMessage().'</error>');
                sleep(600);
            }
	}

	return $events;
    }

    public function getEventStats($id_event)
    {
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

	$request	= $this->client->sendRequest('GET', '/' .$id_event.'/attending', [
            'fields' => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->getGraphPage();
	$partipations	= $graph->getFieldAsArray('data');

	$request	= $this->client->sendRequest('GET', '/' .$id_event.'/maybe', [
            'fields' => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->getGraphPage();
	$interets	= $graph->getFieldAsArray('data');

	return [
	    'participations'	=> count($partipations),
	    'interets'		=> count($interets),
            'membres'           => $partipations + $interets
	];
    }

<<<<<<< HEAD
    public function getEventCountStats($id_event)
    {
	$session	= new FacebookSession($this->siteInfo->getFacebookAccessToken());
	$request	= new FacebookRequest($session, 'GET', '/' .$id_event, [
            'fields' => 'attending_count,maybe_count'
        ]);
	
	$graph		= $request->execute()->getGraphObject(GraphPage::className());

	return [
	    'participations'	=> $graph->getProperty('attending_count'),
	    'interets'		=> $graph->getProperty('maybe_count')
	];
    }

    private function handleEventsEdge($ids, \DateTime $since, $idsPerRequest = 20, $limit = 500)
    {
	$finalEvents = [];
	$nbIterations = ceil(count($ids) / $idsPerRequest);

	for($i = 0; $i < $nbIterations; $i++)
	{
	    $current_ids	= array_slice($ids, $i * $idsPerRequest, $idsPerRequest);
	    $session		= new FacebookSession($this->siteInfo->getFacebookAccessToken());
	    $request		= new FacebookRequest($session, 'GET', '/events', [
		'ids'	    => implode(',', $current_ids),
		'fields'    => self::$MIN_EVENT_FIELDS,
		'since'	    => $since->format('Y-m-d'),
		'limit'	    => $limit
	    ]);

	    try
	    {
		$tmp_datas	= $this->getRecursiveAssociativeRequestDatas($request);
		$finalEvents	= array_merge($finalEvents, $tmp_datas);
		$this->parser->writeln(sprintf('%d / %d : Récupération de <info>%d</info> evenement(s)', $i, $nbIterations, count($tmp_datas)));
	    } catch (FacebookSDKException $ex) {
		$this->parser->writeln('<error>Erreur dans la récupération associatives des pages : '. $ex->getMessage() .'</error>');

		foreach($current_ids as $current_id)
		{
		    try {
			$request    = new FacebookRequest($session, 'GET', '/'.$current_id.'/events', [
				'fields'    => self::$MIN_EVENT_FIELDS,
				'limit'	    => $limit
			    ]);
			$finalEvents   = array_merge($finalEvents, $this->getRecursiveRequestDatas($request));
		    } catch (FacebookSDKException $ex) {
			$this->parser->writeln(sprintf(
				'<error>Erreur dans la récupération des événéments de l\'objet #%s : %s</error>', $current_id, $ex->getMessage()
			));
		    }
		}
	    }
	}

	return $finalEvents;
    }
    
    public function getEventsFromPlaces($places, \DateTime $since, $limit = 500)
    {
	//$places	    = array_slice($places, 0, 50);
	$id_places  = array_map(function(GraphObject $place)
	{
	    return $place->getProperty('id');
	}, $places);

	return $this->handleEventsEdge($id_places, $since);
	
	foreach($places as $place)
	{
	    try {
		$session	    = new FacebookSession($this->siteInfo->getFacebookAccessToken());
		$request	    = new FacebookRequest($session, 'GET', '/'.$place->getProperty('id') .'/events', [
		    'fields'    => self::$MIN_EVENT_FIELDS,
		    'since'     => $since->format('Y-m-d'),
		    'limit'	=> $limit
		]);
		$datas	= $request->execute()->getGraphObjectList();
	    } catch (FacebookSDKException $ex) {
		$this->parser->writeln(sprintf('<error>Erreur dans la récupération des infos de la place #%s : %s</error>', $place->getProperty('id'), $ex->getMessage()));
		$datas = [];
	    }
	    $full_datas = array_merge($full_datas, $datas);
	}

	return $full_datas;
    }

    public function getPlacesFromGPS($latitude, $longitude, $distance, $limit = 500)
    {
	return $this->handlePaginate($this->siteInfo->getFacebookAccessToken(), '/search', [
=======
    public function getPlacesFromGPS($latitude, $longitude, $distance, $limit = 100)
    {        
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        
        $request	= $this->client->sendRequest('GET', '/search', [
>>>>>>> MAJ
            'q'             => '*',
            'type'	    => 'place',
            'center'        => $latitude.','.$longitude,
            'distance'      => $distance,
<<<<<<< HEAD
            'fields'        => 'id'
        ], $limit);
    }

    /**
     * Fonction simple et récursive de bogoss
     * @param string $accessToken
     * @param string $endPoint
     * @param array $params
     * @param int $limit
     * @param int $page
     * @param string $requestMethod
     * @return array
     */
    protected function handlePaginate($accessToken, $endPoint, $params, $limit = 500, $page = 0 , $requestMethod = 'GET')
    {
	//Construction de la requête
	$params['offset']   = $page * $limit;
	$params['limit']    = $limit;
	$session	    = new FacebookSession($accessToken);	
	$request	    = new FacebookRequest($session, $requestMethod, $endPoint, $params);

	//Récupération des données
	return $this->getRecursiveRequestDatas($request);
    }

    protected function getRecursiveAssociativeRequestDatas(FacebookRequest $request)
    {
	$datas = [];

	$response   = $request->execute();
	$graph	    = $response->getGraphObject();
	$indexes    = $graph->getPropertyNames();
	$paging	    = $response->getRequestForNextPage();

	foreach($indexes as $index)
	{
	    $datas = array_merge($datas, $graph->getProperty($index)->getPropertyAsArray('data'));
	}

	if(count($datas) > 0 && $paging)
	{
	    $datas = array_merge($datas, $this->getRecursiveAssociativeRequestDatas($paging));
	}

	return $datas;
    }

    protected function getRecursiveRequestDatas(FacebookRequest $request)
    {
	try {
	    $response	= $request->execute();
	    $graph	= $response->getGraphObject();
	    $datas	= $graph->getPropertyAsArray('data');
	    $paging	= $response->getRequestForNextPage();

	    if(count($datas) > 0 && $paging)
	    {
		$datas = array_merge($datas, $this->getRecursiveRequestDatas($paging));
	    }
	} catch (FacebookSDKException $ex) {
	    $this->parser->writeln(sprintf('<error>Erreur dans la récupération des infos paginées : %s</error>', $ex->getMessage()));
	    $datas	= [];
	}

	return $datas;
    }

    public function getEventsFromUsers($users, \DateTime $since, $limit = 500) {
	//$users		    = array_slice($users, 0, 50);

	return $this->handleEventsEdge($users, $since);
	
        $totalUsers         = count($users);
	$session	    = new FacebookSession($this->siteInfo->getFacebookAccessToken());

	foreach($users as $i => $user)
	{
	    try {
		$start		    = microtime(true);
		$session	    = new FacebookSession($this->siteInfo->getFacebookAccessToken());
		$request	    = new FacebookRequest($session, 'GET', '/'.$user .'/events', [
		    'fields'    => self::$MIN_EVENT_FIELDS,
		    'since'     => $since->format('Y-m-d'),
		    'limit'	=> $limit
		]);
		$datas	    = $request->execute()->getGraphObjectList();
=======
            'fields'        => 'name',
            'limit'         => $limit
        ]);

        $graph	= $request->getGraphEdge();
        
        return $this->findPaginated($graph, function(GraphNode $event) {
            return $event->getField('name');
        });
    }
    
    protected function findPaginated(\Facebook\GraphNodes\GraphEdge $graph, callable $callBack = null)
    {
        $datas = [];
        do {
            if ($graph->getField('error_code')) {
                $this->writeln(sprintf('<error>Erreur #%d : %s</error>', $graph->getField('error_code'), $graph->getField('error_msg')));
                $graph = null;                
            }else
            {
                $currentData    = $callBack ? array_map($callBack, $graph->all()) : $graph->all();
                $datas          = array_merge($datas, $currentData);            
                $graph          = $this->client->next($graph);
            }
        }while($graph !== null && $graph->count() > 0);
        
        return $datas;
    }

    public function getEventsFromUsers($users, \DateTime $since, $limit = 50) {
        $events             = [];
        $usersPerRequest    = 25;
        $totalUsers         = count($users);
        $iterations         = ceil($totalUsers / $usersPerRequest);
	$iterations	    = min($iterations, 2);
        
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        for($i = 0; $i < $iterations; $i++)
        {
            var_dump(implode(',', $currentUsers)); die();
	    $start	    = microtime(true);
            $currentUsers   = array_slice($users, $i*$usersPerRequest, $usersPerRequest);
            $request        = $this->client->sendRequest('GET', '/events', [
                'since'     => $since->format('Y-m-d'),
                'ids'       => implode(',', $currentUsers),
                'fields'    => self::$FIELDS,
                'limit'     => $limit
            ]);            
            
            $response	    = $request->getGraphEdge();
            
            if ($response->getField('error_code')) {
                $this->writeln(sprintf('<error>Erreur #%d : %s</error>', $response->getField('error_code'), $response->getField('error_msg')));
            }else
            {
		$datas		    = [];
                $edges              = $response->getIterator();
                foreach($edges as $edge)
                {
                    var_dump(get_class($edge)); die();
                    $items      = $edge->getArrayCopy();
                    $datas	= array_merge($datas, $items);
                }                

		$events	    = array_merge($events, $datas);
>>>>>>> MAJ
		$end	    = microtime(true);

		$this->parser->writeln(sprintf('%d / %d: <info>%d</info> événement(s) trouvé(s) en <info>%d ms</info>',
			$i, $totalUsers - 1, count($datas), ($end - $start)* 1000));
	    } catch (FacebookSDKException $ex) {
		$this->parser->writeln(sprintf('<error>Erreur dans la récupération des infos de l\'utilisateur #%s : %s</error>', $user, $ex->getMessage()));
		$datas = [];
	    }
	    $events = array_merge($events, $datas);
	}

	return $events;
    }

    public function setParser(AgendaParser $parser)
    {
	$this->parser = $parser;
    }

    public function setSiteInfo(SiteInfo $siteInfo)
    {
        $this->siteInfo = $siteInfo;

        return $this;
    }
}
