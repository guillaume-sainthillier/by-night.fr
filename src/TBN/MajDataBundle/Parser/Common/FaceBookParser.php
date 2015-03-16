<?php

namespace TBN\MajDataBundle\Parser\Common;

use Doctrine\Common\Persistence\ObjectManager;
use Ivory\GoogleMap\Services\Geocoding\Geocoder;
use Facebook\GraphObject;

use TBN\SocialBundle\Social\FacebookAdmin;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MajDataBundle\Entity\BlackList;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\AgendaBundle\Entity\PlaceRepository;

/**
 *
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

	//On ne garde que les événements dont le propriétaire est connu
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
	foreach ($filtered_events as $event) {
	    $i++;
	    $start = microtime(true);
	    $agendas[] = $this->getInfoAgenda($event);
	    $end = microtime(true);
	    $this->writeln(sprintf('%d / %d : Récupération en <info>%f ms</info>', $i, $nbFilteredEvents, ($end - $start)));
	}
	return $agendas;
    }

    protected function getEventsFromPlaces(\DateTime $now)
    {
	//Récupération des lieux existants en  base
	$places		= $this->repoPlace->findBy(['site' => $this->getSite()]);
        $event_places	= array_map(function(Place $place)
        {
	    return $place->getNom();
	}, $places);

	//Récupération des lieux aux alentours de la ville du parser
        $this->write("Recherche d'endroits vers [".$this->getSite()->getLatitude()."; ".$this->getSite()->getLongitude()."]...");
        $fb_places      = $this->api->getPlacesFromGPS($this->getSite()->getLatitude(), $this->getSite()->getLongitude(), $this->getSite()->getDistanceMax()* 10000);
        $this->writeln(" <info>".(count($fb_places))."</info> endroits trouvés");

	//Calcul de toutes les places à traiter
        $full_places    = array_unique(array_merge($event_places, $fb_places));

	//Récupération des événements de l'API marqués comme se tenant aux lieux calculés
	$this->writeln("Recherche d'événements associés aux endroits...");
        $events         = $this->api->searchEventsFromKeywords($full_places, $now);
        $this->writeln("<info>".(count($events))."</info> événements trouvés");
	
        return $events;
    }

    protected function filterEvents($events) {
	$fbIds		    = [];

	return array_filter($events, function(GraphObject $event) use(&$fbIds)
	{
	    $fbId = $event->getProperty("id");
	    //Pas déjà présent & conforme
	    if(!isset($fbIds[$fbId]) && !$this->firewall->isBlackListed($fbId, $this->getSite()))
	    {
		$fbIds[$fbId] = $fbId;
		return true;
	    }
	    return false;
	});
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

    protected function getPageInfos($page) {
	if ($page->getProperty("id")) {
	    $venue = $this->api->getPageFromId($page->getProperty("id"));

	    return [
		"reservation_internet" => $venue->getProperty("website"),
		"reservation_telephone" => $venue->getProperty("phone")
	    ];
	}

	return [];
    }

    /**
     * Retourne les informations d'un événement en fonction de l'ID de cet événement sur Facebook
     * @param type $event
     * @return array l'agenda parsé
     */
    public function getInfoAgenda(GraphObject $event) {
	$tab_retour			    = [];
	
	$tab_retour["nom"]		    = $event->getProperty("name");
	$tab_retour["facebook_event_id"]    = $event->getProperty("id");
	$tab_retour["descriptif"]	    = nl2br($event->getProperty("description"));
	$tab_retour["place.nom"]	    = $event->getProperty("location");
	$tab_retour["date_debut"]	    = $this->getDateFromEvent($event, "start_time");
	$tab_retour["date_fin"]		    = $this->getDateFromEvent($event, "end_time");


	//Horaires
	if (!$event->getProperty("is_date_only")) //Des dates & heures précises sont remplies
	{
	    $dateDebut	= $tab_retour["date_debut"];
	    $dateFin	= $tab_retour["date_fin"];
	    
	    if ($dateFin && $this->isSameDay($dateDebut, $dateFin)) {
		$horaires = sprintf("De %s à %s", $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
	    } else {
		$horaires = sprintf("A %s", $dateDebut->format("H\hi"));
	    }

	    $tab_retour["horaires"] = $horaires;
	}        

        //Image
	$tab_retour["url"]		    = $this->api->getPagePictureURL($event);
        
        //Reservations
	$tab_retour["reservation_internet"] = $event->getProperty("ticket_uri");

	if ($event->getProperty("venue")) {
	    $venue = $event->getProperty("venue");

	    //Résa URL & Téléphone
	    $tab_retour = array_merge($tab_retour, $this->getPageInfos($venue));

	    //Lieux
	    $tab_retour["place.rue"] = $venue->getProperty("street");
	    $tab_retour["place.latitude"] = $venue->getProperty("latitude");
	    $tab_retour["place.longitude"] = $venue->getProperty("longitude");
	    $tab_retour["place.ville.code_postal"] = $venue->getProperty("zip");
	    $tab_retour["place.ville.nom"] = $venue->getProperty("city");
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
