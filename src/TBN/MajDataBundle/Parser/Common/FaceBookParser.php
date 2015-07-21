<?php

namespace TBN\MajDataBundle\Parser\Common;

use Doctrine\Common\Persistence\ObjectManager;
use Facebook\GraphObject;

use TBN\SocialBundle\Social\FacebookAdmin;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\AgendaBundle\Entity\PlaceRepository;

/**
 * Classe de parsing des événéments FB
 * @author Guillaume SAINTHILLIER
 */
class FaceBookParser extends AgendaParser {

    /**
     * @var FacebookAdmin $api
     */
    protected $api;

    /**
     *
     * @var AgendaRepository
     */
    protected $repoEvent;

    /**
     *
     * @var PlaceRepository
     */
    protected $repoPlace;
    
    /**
     *
     * @var Firewall
     */
    protected $firewall;


    public function __construct(ObjectManager $om, Firewall $firewall, FacebookAdmin $api) {
	parent::__construct();
	
	$this->firewall		= $firewall;
	$this->api              = $api;
	$this->repoEvent	= $om->getRepository('TBNAgendaBundle:Agenda');
	$this->repoPlace	= $om->getRepository('TBNAgendaBundle:Place');
    }


    public function getRawAgendas() {
	$this->api->setSiteInfo($this->getSiteInfo());
	$this->api->setParser($this);
        
	$agendas        = [];
        $now            = new \DateTime;

	//Recherche d'événements de l'API en fonction de lieux déjà connus dans la BD
        $place_events   = $this->getEventsFromPlaces($now);

	//Calcul de l'ID FB des propriétaires des événements précédemment trouvés
        $event_users = array_map(function(GraphObject $event)
        {
            $owner = $event->getProperty("owner");
            return $owner ? $owner->getProperty("id") : null;
        }, $place_events);

	//On ne garde que les événements dont le propriétaire est renseigné
        $real_event_users = array_filter($event_users);

        //Récupération en base des différents ID des utilisateurs FB
	$this->write('Recherche des propriétaires FB existants...');
	$fb_events  = $this->repoEvent->getEventsWithFBOwner($this->getSite()); //Les events sont groupés par ID FB, pas de doublon donc
        $fb_users   = array_map(function(Agenda $agenda)
        {
	    return $agenda->getFacebookOwnerId();
	}, $fb_events);

        //Fusion et tri de tous les propriétaires d'événement trouvés
	$full_users     = array_unique(array_merge($fb_users, $real_event_users));
        $this->writeln('<info>'.count($full_users).'</info> propriétaires trouvés');

	//Récupération de tous les événements depuis les propriétaires
        $this->writeln('Recherche d\'événements associés aux propriétaires...');
	$user_events    = $this->api->getEventsFromUsers($full_users, $now);
        $this->writeln(sprintf('<info>%d</info> événement(s) trouvé(s)', count($user_events)));
        
        //Construction de tous les événements
        $events         = array_merge($place_events, $user_events);

        //Filtrage des événements
        $this->writeln(sprintf('Pré-filtrage de <info>%d</info> événement(s)...', count($events)));
        $filtered_events    = $this->filterEvents($events);
	$nbFilteredEvents   = count($filtered_events);
        $this->writeln(sprintf('<info>%d</info> événéments retenus, récupération des infos', $nbFilteredEvents));

	//Création des instances d'Agenda
	$i = 0;
	$eventsPerRequest = 30;
	$nbIterations = ceil($nbFilteredEvents / $eventsPerRequest);
	for($i = 0; $i < $nbIterations; $i++)
	{
	    $currentIdsEvents	= array_slice($filtered_events, $i* $eventsPerRequest, $eventsPerRequest);
	    $start		= microtime(true);
	    $events		= $this->api->getEventsFromIds($currentIdsEvents);

	    foreach ($events as $event) {
		$agendas[] = $this->getInfoAgenda($event);
	    }
	    $end = microtime(true);
	    $this->writeln(sprintf('%d / %d : Récupération en <info>%d ms</info>', $i, $nbIterations, ($end - $start)*1000));
	}
	/*
	//Création des instances d'Agenda
	foreach ($filtered_events as $i => $id_event) {
	    try {
		$start		= microtime(true);
		$event		= $this->api->getEventFromId($id_event);
		$agendas[]	= $this->getInfoAgenda($event);
		$end		= microtime(true);
		$this->writeln(sprintf('%d / %d : Récupération en <info>%d ms</info>', $i, $nbFilteredEvents, ($end - $start)*1000));
	    } catch (\Facebook\FacebookSDKException $ex) {
		$this->writeln(sprintf('<error>Erreur dans la récupération de l\'événement #%s : %s</error>', $id_event, $ex->getMessage()));
	    }
	}*/

	return $agendas; 
    }

    protected function getEventsFromPlaces(\DateTime $now)
    {
	//Récupération des lieux existants en  base
	$places		= $this->repoPlace->findBy(['site' => $this->getSite()]);
        $nom_places	= array_map(function(Place $place)
        {
	    return $place->getNom();
	}, $places);

	//Récupération des lieux aux alentours de la ville courante
        $this->write("Recherche d'endroits vers [".$this->getSite()->getLatitude()."; ".$this->getSite()->getLongitude()."]...");
        $fb_places      = $this->api->getPlacesFromGPS($this->getSite()->getLatitude(), $this->getSite()->getLongitude(), $this->getSite()->getDistanceMax()* 10000);
        $this->writeln(" <info>".(count($fb_places))."</info> places trouvées");

	//Récupération des événements depuis les lieux trouvés
	$this->write("Recherche des événements associés aux endroits ...");
	$places_events	= $this->api->getEventsFromPlaces($fb_places, $now);
	$this->writeln("<info>".(count($places_events))."</info> événements trouvés");

	//Récupération des événements par mots-clés
	$this->writeln("Recherche d'événements associés aux mots clés...");
        $keywords_events        = $this->api->searchEvents($nom_places, $now);
	$events			= array_merge($keywords_events, $places_events);
        $this->writeln("<info>".(count($events))."</info> événements trouvés");
	
        return $events;
    }

    protected function filterEvents($events) {
	$filtered = array_filter($events, function(GraphObject $event)
	{
	    $exploration = $this->firewall->getExploration($event->getProperty('id'), $this->getSite());

	    $lastUpdatedEventTime	= new \DateTime($event->getProperty('updated_time'));
	    $lastUpdatedExplorationTime = $exploration->getLastUpdated();

	    $exploration->setLastUpdated($lastUpdatedEventTime);

	    //Pas blacklisté et jamais exploré ou déjà exploré mais expiré
	    return true !== $exploration->getBlackListed() && !$this->isSameTime($lastUpdatedEventTime, $lastUpdatedExplorationTime);
	});

	return array_unique(array_map(function(GraphObject $event)
	{
	    return $event->getProperty('id');
	}, $filtered));
    }

    protected function getDateFromEvent($event, $key) {
	$str_date = $event->getProperty($key);
	if ($str_date === null) {
	    return null;
	}

	return \DateTime::createFromFormat($event->getProperty("is_date_only") ? "Y-m-d" : "Y-m-d\TH:i:sP", $str_date);
    }

    public function isTrustedLocation() {
	return false; //On ne connait pas ici le lieu réel de l'événement qui peut se situer n'importe où dans le monde
    }

    private function isSameDay($date1, $date2) {
	return $date1->format("Y-m-d") === $date2->format("Y-m-d");
    }

    private function isSameTime(\DateTime $date1 = null, \DateTime $date2 = null) {
	if(! $date1 || ! $date2) //Non prmissif
	{
	    return false;
	}

	return $date1->format("Y-m-d H:i:s") === $date2->format("Y-m-d H:i:s");
    }

    protected function getPageInfos($page) {

	$id_page = $page->getProperty('id');

	if($id_page)
	{
	    try
	    {
		$place = $this->api->getPageFromId($id_page, [
		    'fields' => 'website,phone,picture.type(large).redirect(false)'
		]);

		return [
		    "reservation_internet"	=> $place->getProperty('website'),
		    "reservation_telephone"	=> $place->getProperty("phone"),
		    "place.url"			=> $this->api->getPagePictureURL($place, false)
		];
	    } catch(\Facebook\FacebookSDKException $ex) {}
	}
	return [];
    }

    /**
     * Retourne les informations d'un événement en fonction de l'ID de cet événement sur Facebook
     * @param $event
     * @return array l'agenda parsé
     */
    public function getInfoAgenda(GraphObject $event) {

	$tab_retour			    = [];
	
	$tab_retour["nom"]		    = $event->getProperty("name");
	$tab_retour["facebook_event_id"]    = $event->getProperty("id");
	$tab_retour["descriptif"]	    = nl2br($event->getProperty("description"));
	$tab_retour["date_debut"]	    = $this->getDateFromEvent($event, "start_time");
	$tab_retour["date_fin"]		    = $this->getDateFromEvent($event, "end_time");
	$tab_retour["fb_participations"]    = $event->getProperty("attending_count");
	$tab_retour["fb_interets"]	    = $event->getProperty("maybe_count");


	//Horaires
	if (!$event->getProperty("is_date_only")) //Des dates & heures précises sont remplies
	{
	    $dateDebut	= $tab_retour["date_debut"];
	    $dateFin	= $tab_retour["date_fin"];
	    $horaires	= null;
	    
	    if ($dateDebut instanceof \DateTime && $dateFin instanceof \DateTime && $this->isSameDay($dateDebut, $dateFin)) {
		$horaires = sprintf("De %s à %s", $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
	    } elseif($dateDebut instanceof \DateTime) {
		$horaires = sprintf("A %s", $dateDebut->format("H\hi"));
	    }

	    $tab_retour["horaires"] = $horaires;
	}        

        //Image
	$tab_retour["url"]		    = $this->api->getPagePictureURL($event);
        
        //Reservations
	$tab_retour["reservation_internet"] = $event->getProperty("ticket_uri");

	//Place
	$place = $event->getProperty("place");
	if ($place) {

	    $tab_retour["place.nom"] = $place->getProperty("name");

	    //Résa URL & Téléphone
	    $tab_retour = array_merge($tab_retour, $this->getPageInfos($place));

	    //Location
	    $location		    = $place->getProperty('location');
	    if($location)
	    {
		$tab_retour["place.rue"]		= $location->getProperty("street");
		$tab_retour["place.latitude"]		= $location->getProperty("latitude");
		$tab_retour["place.longitude"]		= $location->getProperty("longitude");
		$tab_retour["place.ville.code_postal"]	= $location->getProperty("zip");
		$tab_retour["place.ville.nom"]		= $location->getProperty("city");
	    }	    
	}

	//Propriétaire de l'événement
	$owner = $event->getProperty("owner");
	if ($owner) {
	    $tab_retour["facebook_owner_id"] = $owner->getProperty("id");
	}

	return $tab_retour;
    }
    
    public function getNomData() {
	return "Facebook";
    }
}
