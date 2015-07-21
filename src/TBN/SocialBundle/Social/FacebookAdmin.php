<?php

namespace TBN\SocialBundle\Social;


use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;

use Facebook\GraphPage;
use Facebook\GraphNodes\GraphNode;

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

    public function __construct($config, \TBN\MainBundle\Site\SiteManager $siteManager, \Symfony\Component\Security\Core\SecurityContextInterface $securityContext, \Symfony\Component\Routing\RouterInterface $router, \Symfony\Component\HttpFoundation\Session\SessionInterface $session, \Symfony\Component\HttpFoundation\RequestStack $requestStack) {
        parent::__construct($config, $siteManager, $securityContext, $router, $session, $requestStack);

        $this->siteInfo = $this->siteManager->getSiteInfo();
	$this->cache	= [];
        $this->parser	= null;
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
		return $page->getField('likes');
	    } catch (\Exception $ex) {
	    }
	}

	return 0;
    }

    public function getPageFromId($id_page)
    {
	$key = 'pages.'.$id_page;
	if(! isset($this->cache[$key]))
	{
            $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	    $response = $this->client->get('/' .$id_page);

	    $this->cache[$key] = $response->getGraphPage();
	}

	return $this->cache[$key];
    }
    
    public function searchEventsFromKeywords($keywords, \DateTime $since, $limit = 50) {
        
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	$keywords   = array_slice($keywords, 0, 5);

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
                    'fields'	    => self::$FIELDS,
                    'limit'	    => $limit
                ]);

                $graph	= $request->getGraphEdge();
                $data	= $graph->getIterator();
                $events	= array_merge($events, $data->getArrayCopy());
		$end	= microtime(true);
		$this->parser->writeln(sprintf('%d / %d: <info>%d</info> événement(s) trouvé(s) pour %s en <info>%d ms</info>',
			$i, $nbKeywords - 1, count($data), $keyword, ($end - $start)* 1000));
            }catch(\Exception $e)
            {
                $this->parser->writeln('Erreur dans la recherche par mot-clé : '.$e->getMessage());
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

    public function getPlacesFromGPS($latitude, $longitude, $distance, $limit = 100)
    {        
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        
        $request	= $this->client->sendRequest('GET', '/search', [
            'q'             => '*',
            'type'	    => 'place',
            'center'        => $latitude.','.$longitude,
            'distance'      => $distance,
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
		$end	    = microtime(true);
		
		$this->parser->writeln(sprintf('%d / %d: <info>%d</info> événement(s) trouvé(s) en <info>%d ms</info>',
			$i, $iterations - 1, count($datas), ($end - $start)* 1000));
		
            }
        }

	return $events;
    }
}
