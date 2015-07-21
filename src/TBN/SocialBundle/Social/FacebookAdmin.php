<?php

namespace TBN\SocialBundle\Social;


use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;

use Facebook\GraphPage;
use Facebook\GraphObject;

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
	    $session = new FacebookSession($this->siteInfo->getFacebookAccessToken());
	    $request = new FacebookRequest($session, 'POST', '/' . $site->getFacebookIdPage() . '/feed', [
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

	    $post = $request->execute()->getGraphObject();

	    $agenda->setFbPostSystemId($post->getProperty('id'));
	}
    }

    public function getNumberOfCount() {
	$site   = $this->siteManager->getCurrentSite();

	if ($site !== null && $this->siteInfo !== null) {
	    try {
		$page = $this->getPageFromId($site->getFacebookIdPage());                
		return $page->getProperty('likes');
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
    
    public function searchEventsFromKeywords($keywords, \DateTime $since, $limit = 5000) {
	$session    = new FacebookSession($this->siteInfo->getFacebookAccessToken());
	$events	    = [];
	$keywords   = array_slice($keywords, 0, 1);

	$nbKeywords = count($keywords);
	//Récupération des events en fonction d'un mot-clé
	foreach($keywords as $i => $keyword)
	{
            try {
		$start = microtime(true);
                $request	= new FacebookRequest($session, 'GET', '/search', [
                    'q'		    => $keyword,
                    'type'	    => 'event',
                    'since'	    => $since->format('Y-m-d'),
                    'fields'	    => self::$FIELDS,
                    'limit'	    => $limit
                ]);

                $graph	= $request->execute()->getGraphObject();
                $data	= $graph->getPropertyAsArray('data');
                $events	= array_merge($events, $data);
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
	$session	= new FacebookSession($this->siteInfo->getFacebookAccessToken());

	$request	= new FacebookRequest($session, 'GET', '/' .$id_event.'/attending', [
            'fields' => static::$ATTENDING_FIELDS
        ]);
	$graph		= $request->execute()->getGraphObject(GraphPage::className());
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

    public function getPlacesFromGPS($latitude, $longitude, $distance, $limit = 5000)
    {
        $session        = new FacebookSession($this->siteInfo->getFacebookAccessToken());
        $request	= new FacebookRequest($session, 'GET', '/search', [
            'q'             => '*',
            'type'	    => 'place',
            'center'        => $latitude.','.$longitude,
            'distance'      => $distance,
            'fields'        => 'name',
            'limit'	    => $limit
        ]);

        $graph	= $request->execute()->getGraphObject();
        $data	= $graph->getPropertyAsArray('data');

        return array_map(function(GraphObject $place)
        {
            return $place->getProperty('name');
        }, $data);
    }

    public function getEventsFromUsers($users, \DateTime $since, $limit = 5000) {
        $events             = [];
        $usersPerRequest    = 50;
        $totalUsers         = count($users);
        $iterations         = ceil($totalUsers / $usersPerRequest);
	$iterations	    = 2;
	$session	    = new FacebookSession($this->siteInfo->getFacebookAccessToken());
        for($i = 0; $i < $iterations; $i++)
        {
	    $start	    = microtime(true);
            $currentUsers   = array_slice($users, $i*$usersPerRequest, $usersPerRequest);
            
            $request        = new FacebookRequest($session, 'GET', '/events', [
                'since'     => $since->format('Y-m-d'),
                'ids'       => implode(',', $currentUsers),
                'fields'    => self::$FIELDS,
                'limit'     => $limit
            ]);

            $graph	    = $request->execute()->getGraphObject();
            if ($graph->getProperty('error_code')) {
                $this->writeln(sprintf('<error>Erreur #%d : %s</error>', $graph->getProperty('error_code'), $graph->getProperty('error_msg')));
            }else
            {
		$datas		    = [];
                $real_owner_ids     = $graph->getPropertyNames();
                foreach ($real_owner_ids as $id)
                {
                    $owner_events   = $graph->getProperty($id);
		    $data	    = $owner_events->getPropertyAsArray('data');
                    $datas	    = array_merge($datas, $data);
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
