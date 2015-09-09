<?php

namespace TBN\SocialBundle\Social;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use JMS\Serializer\SerializerInterface;

use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\MainBundle\Site\SiteManager;


use Facebook\GraphNodes\GraphPage;
use Facebook\GraphNodes\GraphNode;
use Facebook\GraphNodes\GraphEdge;
use Facebook\FacebookResponse;
use Facebook\Exceptions\FacebookSDKException;

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
    
    /**
     *
     * @var SerializerInterface 
     */
    protected $serializer;

    public function __construct($config, SiteManager $siteManager, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, ObjectManager $om, SerializerInterface $serializer) {
        parent::__construct($config, $siteManager, $tokenStorage, $router, $session, $requestStack);

        $this->siteInfo = $this->siteManager->getSiteInfo();
        $this->serializer = $serializer;
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
	    } catch (FacebookSDKException $ex) {}
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

    public function getEventsFromIds(& $ids_event, $idsPerRequest = 20, $limit = 500)
    {
        $requestPerBatch = 50;
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs   = ceil(count($ids_event) / $idsPerBatch);
	$finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

        for($i = 0; $i < $nbBatchs; $i++)
        {
            $requests = [];
            $batch_ids = array_slice($ids_event, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);
            try
            {
                for($j = 0; $j < $nbIterations; $j++)
                {
                    $current_ids	= array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[]        = $this->client->request('GET', '/', [
                        'ids'	    => implode(',', $current_ids),
                        'fields'    => self::$FIELDS,
                        'limit'	    => $limit
                    ]);
                }
                
                //Exécution du batch
                $start = microtime(true);
                $responses = $this->client->sendBatchRequest($requests);
                
                //Traitement des réponses
                $fetchedEvents = 0;
                foreach ($responses as $response) {
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        $this->parser->writeln('<error>Erreur dans le batch de la recherche par IDS événements : '.($e ? $e->getMessage() : 'Erreur Inconnue').'</error>');
                    } else {
                        $datas  = $this->findAssociativeEvents($response);
                        $fetchedEvents += count($datas);
                        $finalEvents	= array_merge($finalEvents, $datas);
                    }
                }
                $end	= microtime(true);
                $this->parser->writeln(sprintf('%d / %d : Récupération détaillée de <info>%d</info> événement(s) en %d ms', $i + 1, $nbBatchs, $fetchedEvents, 1000 * ($end - $start)));
            } catch (FacebookSDKException $ex) {
                $this->parser->writeln('<error>Erreur dans la récupération détaillée des événements : '. $ex->getMessage() .'</error>');

                foreach($batch_ids as $current_id)
                {
                    try {
                        $finalEvents   = array_merge($finalEvents, [$this->getEventFromId($current_id)]);
                    } catch (FacebookSDKException $ex) {
                        $this->parser->writeln(sprintf(
                                '<error>Erreur dans la récupération l\'événement #%s : %s</error>', $current_id, $ex->getMessage()
                        ));
                    }
                }
            }
	}

	return $finalEvents;
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
    
    public function searchEvents(& $keywords, \DateTime $since, $limit = 500) {
	$this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	$nbKeywords = count($keywords);
        $requestsParBatch = 50;
        
        $nbBatchs   = ceil($nbKeywords / $requestsParBatch);
        $nbBatchs   = 1; //TODO: SUPPRIMER CA
        
	//Récupération des events en fonction des mot-clés
        for($i = 0; $i < $nbBatchs; $i++)
        {
            try {
                $requests = [];
                $current_keywords = array_slice($keywords, $i * $requestsParBatch, $requestsParBatch);
                foreach($current_keywords as $keyword)
                {
                    //Construction des requêtes du batch
                    $requests[]	= $this->client->request('GET', '/search?', [
                        'q'         => $keyword,
                        'type'	    => 'event',
                        'since'	    => $since->format('Y-m-d'),
                        'fields'    => self::$MIN_EVENT_FIELDS,
                        'limit'	    => $limit
                    ], $this->siteInfo->getFacebookAccessToken());
                }

                //Exécution du batch
                $start = microtime(true);
                $responses = $this->client->sendBatchRequest($requests);
                
                //Traitement des réponses
                $fetchedEvents = 0;
                foreach ($responses as $response) {
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        $this->parser->writeln('<error>Erreur dans le batch de la recherche par mot-clés : '.($e ? $e->getMessage() : 'Erreur Inconnue').'</error>');
                    } else {
                        $datas  = $this->findPaginated($response->getGraphEdge());
                        $fetchedEvents += count($datas);
                        $events	= array_merge($events, $datas);
                    }
                }

                $end	= microtime(true);
                        $this->parser->writeln(sprintf('%d / %d: <info>%d</info> événement(s) trouvé(s) pour %d mots-clés en <info>%d ms</info>',
                                $i + 1 , $nbBatchs, $fetchedEvents, count($current_keywords), ($end - $start)* 1000));
            }catch(FacebookSDKException $e)
            {
                $this->parser->writeln('<error>Erreur dans la recherche par mot-clé : '.$e->getMessage().'</error>');
            }
        }

	return $events;
    }
    
    protected function findPaginated(GraphEdge $graph = null, callable $callBack = null)
    {
        $datas = [];
        
        if($graph !== null)
        {
            try {
                do {
                    if ($graph->getField('error_code')) {
                        $this->parser->writeln(sprintf('<error>Erreur #%d : %s</error>', $graph->getField('error_code'), $graph->getField('error_msg')));
                        $graph = null;                
                    }else
                    {
                        $currentData    = $callBack ? array_map($callBack, $graph->all()) : $graph->all();
                        $datas          = array_merge($datas, $currentData);
                        $graph          = $this->client->next($graph);
                    }
                }while($graph !== null && $graph->count() > 0);
            } catch (FacebookSDKException $ex) {
                $this->parser->writeln(sprintf('<error>Erreur dans findPaginated : %s</error>', $ex->getMessage()));
            }
        }        
        
        return $datas;
    }
    
    protected function findAssociativeEvents(FacebookResponse $response)
    {
        $graph      = $response->getGraphNode();
        $indexes    = $graph->getFieldNames();
        
        return array_map(function($index) use($graph)
        {
            return $graph->getField($index);
        }, $indexes);
    }
    
    protected function findAssociativePaginated(FacebookResponse $response)
    {
        $datas      = [];
        $graph      = $response->getGraphNode();
        $indexes    = $graph->getFieldNames();
        foreach($indexes as $index)
        {
            $subGraph = $graph->getField($index);
            $datas = array_merge($datas, $this->findPaginated($subGraph));
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

    private function handleEventsEdge(& $ids, \DateTime $since, $idsPerRequest = 50, $limit = 500)
    {
        $requestPerBatch = 50;
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs   = ceil(count($ids) / $idsPerBatch);
	$finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

        for($i = 0; $i < $nbBatchs; $i++)
        {
            $requests = [];
            $batch_ids = array_slice($ids, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);
            try
            {
                for($j = 0; $j < $nbIterations; $j++)
                {
                    $current_ids	= array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[]        = $this->client->request('GET', '/events', [
                        'ids'	    => implode(',', $current_ids),
                        'fields'    => self::$MIN_EVENT_FIELDS,
                        'since'	    => $since->format('Y-m-d'),
                        'limit'	    => $limit
                    ]);
                }
                
                //Exécution du batch
                $start = microtime(true);
                $responses = $this->client->sendBatchRequest($requests);
                
                //Traitement des réponses
                $fetchedEvents = 0;
                foreach ($responses as $response) {
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        $this->parser->writeln('<error>Erreur dans le batch de la recherche par événements : '.($e ? $e->getMessage() : 'Erreur Inconnue').'</error>');
                    } else {
                        $datas  = $this->findAssociativePaginated($response);
                        $fetchedEvents += count($datas);
                        $finalEvents	= array_merge($finalEvents, $datas);
                    }
                }
                $end	= microtime(true);
                $this->parser->writeln(sprintf('%d / %d : Récupération de <info>%d</info> événement(s) en %d ms', $i + 1, $nbBatchs, $fetchedEvents, 1000 * ($end - $start)));

            } catch (FacebookSDKException $ex) {
                $this->parser->writeln('<error>Erreur dans la récupération associatives des pages : '. $ex->getMessage() .'</error>');

                foreach($batch_ids as $current_id)
                {
                    try {
                        $request    = $this->client->sendRequest('GET', '/'.$current_id.'/events', [
                                'fields'    => self::$MIN_EVENT_FIELDS,
                                'limit'	    => $limit
                            ]);
                        $finalEvents   = array_merge($finalEvents, $this->findPaginated($request->getGraphEdge()));
                    } catch (FacebookSDKException $ex) {
                        $this->parser->writeln(sprintf(
                                '<error>Erreur dans la récupération des événéments de l\'objet #%s : %s</error>', $current_id, $ex->getMessage()
                        ));
                    }
                }
            }
	}

	return array_unique($finalEvents);
    }
    
    public function getEventsFromPlaces(& $places, \DateTime $since)
    {
	$places	    = array_slice($places, 0, 5); // TODO: SUPPRIMER CA
	$id_places  = array_map(function(GraphNode $place)
	{
	    return $place->getField('id');
	}, $places);

	return $this->handleEventsEdge($id_places, $since, 40);
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
        try {
            //Construction de la requête
            $params['offset']   = $page * $limit;
            $params['limit']    = $limit;	
            $request            = $this->client->sendRequest($requestMethod, $endPoint, $params, $accessToken);

            //Récupération des données
            return $this->findPaginated($request->getGraphEdge());
        } catch (FacebookSDKException $ex) {
            $this->parser->writeln(sprintf('<error>Erreur dans handlePaginate : %s</error>', $ex->getMessage()));
        }
	
        return [];
    }

    public function getEventsFromUsers(& $users, \DateTime $since) {
	return $this->handleEventsEdge($users, $since, 40);
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