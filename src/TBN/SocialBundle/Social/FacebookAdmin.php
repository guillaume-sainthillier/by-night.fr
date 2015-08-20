<?php

namespace TBN\SocialBundle\Social;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\MainBundle\Site\SiteManager;


use Facebook\GraphNodes\GraphPage;
use Facebook\GraphNodes\GraphNode;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;

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

    public function __construct($config, SiteManager $siteManager, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, ObjectManager $om) {
        parent::__construct($config, $siteManager, $tokenStorage, $router, $session, $requestStack);

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
            $message    = $user->getUsername() . ' présente : '. $agenda->getNom().' @ '.$agenda->getLieuNom();

            //Authentification
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
	    ], $this->siteInfo->getFacebookAccessToken());

	    $post = $request->getGraphNode();
	    $agenda->setFbPostSystemId($post->getField('id'));
	}
    }

    public function getNumberOfCount() {
	$site   = $this->siteManager->getCurrentSite();

	if ($site !== null && $this->siteInfo !== null) {
	    try {
		$page = $this->getPageFromId($site->getFacebookIdPage());                
		return $page->getField('likes');
	    } catch (\Exception $ex) {}
	}

	return 0;
    }

    public function getEventFromId($id_event, $fields = null)
    {
	$key = 'events'.$id_event;
	if(! isset($this->cache[$key]))
	{
	    $request = $this->client->sendRequest('GET', '/' .$id_event, [
		'fields' => $fields ?: self::$FIELDS
	    ], $this->siteInfo->getFacebookAccessToken());
	    
	    $this->cache[$key] = $request->getGraphEvent();
	}

	return $this->cache[$key];
    }

    public function getEventsFromIds($ids_event)
    {
	try {
	    $request		= $this->client->sendRequest('GET', '/', [
		'ids'	    => implode(',', $ids_event),
		'fields'    => self::$FIELDS
	    ], $this->siteInfo->getFacebookAccessToken());
            
            $events     = [];
            $graph      = $request->getGraphNode();
            $indexes    = $graph->getFieldNames();
            foreach($indexes as $index)
            {
                $events[] = $graph->getField($index);
            }
            
	    return $events;
            
	} catch (FacebookSDKException $ex) {
	    $this->parser->writeln('<error>Erreur dans la récupération des pages : '. $ex->getMessage() .'</error>');

	    return array_map(function($id_event)
	    {
		try {
		    return $this->getEventFromId($id_event);
		} catch (FacebookSDKException $ex) {
		    $this->parser->writeln(sprintf(
                        '<error>Erreur dans la récupération de l\'événement #%s : %s</error>', 
                        $id_event, 
                        $ex->getMessage()
		    ));
		}
	    }, $ids_event);
	}
    }

    public function getPageFromId($id_page, $params = [])
    {
	$key = 'pages.'.$id_page;
	if(! isset($this->cache[$key]))
	{
	    $request = $this->client->sendRequest('GET', 
                '/' . $id_page, 
                $params, 
                $this->siteInfo->getFacebookAccessToken()
            );

	    $this->cache[$key] = $request->getGraphPage();
	}

	return $this->cache[$key];
    }
    
    public function searchEvents($keywords, \DateTime $since, $limit = 500) {
	$this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	$keywords   = array_slice($keywords, 0, min(100, count($keywords))); //TODO: SUPPRIMER CA
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
                ], $this->siteInfo->getFacebookAccessToken());

                $datas	= $this->findPaginated($request->getGraphEdge());
                $events	= array_merge($events, $datas);
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
    
    protected function findAssociativePaginated(FacebookResponse $response)
    {
        $datas      = [];
        $graph      = $response->getGraphNode();
        $indexes    = $graph->getFieldNames();
        foreach($indexes as $index)
        {
            $subGraph = $graph->getField($index);
            $datas = array_merge($datas, $graph->getField($index)->all());
            
            $next = $subGraph->getPaginationUrl('next');
            if($next)
            {
                $datas = $this->findPaginated($this->client->get($next)->getGraphEdge());
            }
        }
        
        return $datas;
    }

    public function getEventStats($id_event)
    {
        return [
	    'participations'	=> 0,
	    'interets'		=> 0,
            'membres'           => []
	];
        
	$this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

	$request	= $this->client->sendRequest('GET', '/' .$id_event.'/attending', [
            'fields' => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->getGraphPage();
	$partipations	= $graph->getPropertyAsArray('data');

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event.'/maybe', [
            'fields' => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
	$interets	= $graph->getPropertyAsArray('data');

	return [
	    'participations'	=> count($partipations),
	    'interets'		=> count($interets),
            'membres'           => $partipations + $interets
	];
    }

    public function getEventCountStats($id_event)
    {
	$request	= $this->client->sendRequest('GET', '/' .$id_event, [
            'fields' => 'attending_count,maybe_count'
        ], $this->siteInfo->getFacebookAccessToken());
	
	$graph		= $request->getGraphPage();

	return [
	    'participations'	=> $graph->getField('attending_count'),
	    'interets'		=> $graph->getField('maybe_count')
	];
    }

    private function handleEventsEdge($ids, \DateTime $since, $idsPerRequest = 20, $limit = 1000)
    {
	$finalEvents = [];
	$nbIterations = ceil(count($ids) / $idsPerRequest);
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

	for($i = 0; $i < $nbIterations; $i++)
	{
            $current_ids	= array_slice($ids, $i * $idsPerRequest, $idsPerRequest);
	    try
	    {                
                $request        = $this->client->sendRequest('GET', '/events', [
                    'ids'	    => implode(',', $current_ids),
                    'fields'        => self::$MIN_EVENT_FIELDS,
                    'since'	    => $since->format('Y-m-d'),
                    'limit'	    => $limit
                ]);
                
                $currentEvents  = $this->findAssociativePaginated($request);
		$finalEvents	= array_merge($finalEvents, $currentEvents);
		$this->parser->writeln(sprintf('%d / %d : Récupération de <info>%d</info> evenement(s)', $i, $nbIterations, count($currentEvents)));
	    } catch (FacebookSDKException $ex) {
		$this->parser->writeln('<error>Erreur dans la récupération associatives des pages : '. $ex->getMessage() .'</error>');

		foreach($current_ids as $current_id)
		{
		    try {
			$request    = $this->client->sendRequest('GET', '/'.$current_id.'/events', [
				'fields'    => self::$MIN_EVENT_FIELDS,
				'limit'	    => $limit
			    ]);
			$finalEvents   = array_merge($finalEvents, $this->findPaginated($request));
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
	$places	    = array_slice($places, 0, 100); // TODO: SUPPRIMER CA
	$id_places  = array_map(function(GraphNode $place)
	{
	    return $place->getField('id');
	}, $places);

	return $this->handleEventsEdge($id_places, $since);
    }

    public function getPlacesFromGPS($latitude, $longitude, $distance, $limit = 500)
    {
	return $this->handlePaginate($this->siteInfo->getFacebookAccessToken(), '/search', [
            'q'             => '*',
            'type'	    => 'place',
            'center'        => $latitude.','.$longitude,
            'distance'      => $distance,
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
    protected function handlePaginate($accessToken, $endPoint, $params, $limit = 500, $page = 0, $requestMethod = 'GET')
    {
	//Construction de la requête
	$params['offset']   = $page * $limit;
	$params['limit']    = $limit;	
	$request	    = $this->client->sendRequest($requestMethod, $endPoint, $params, $accessToken);

	//Récupération des données
	return $this->findPaginated($request->getGraphEdge());
    }

    public function getEventsFromUsers($users, \DateTime $since) {
	return $this->handleEventsEdge($users, $since);
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