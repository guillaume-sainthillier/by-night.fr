<?php

namespace TBN\MajDataBundle\Parser\Common;

use Doctrine\Common\Persistence\ObjectManager;
use Facebook\GraphNodes\GraphNode;

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

        $now            = new \DateTime;

	//Recherche d'événements de l'API en fonction de lieux déjà connus dans la BD
        $place_events   = $this->getEventsFromPlaces($now);

	//Calcul de l'ID FB des propriétaires des événements précédemment trouvés
        $event_users = array_map(function(GraphNode $event)
        {
            $owner = $event->getField('owner');
            return $owner ? $owner->getField('id') : null;
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
	$full_users     = array_unique(array_filter(array_merge($fb_users, $real_event_users)));
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
        
        //Libération de la RAM
        unset($place_events);
        unset($event_users);
        unset($fb_events);
        unset($real_event_users);
        unset($user_events);
        unset($full_users);
        unset($fb_users);
        unset($events);

        //Récupération des événements par Batch
        return array_map([$this, 'getInfoAgenda'], $this->api->getEventsFromIds($filtered_events));
    }

    protected function getEventsFromPlaces(\DateTime $now)
    {
	//Récupération des lieux existants en  base
	$places		= $this->repoPlace->findBy(['site' => $this->getSite()]);
        $nom_places	= array_filter(array_map(function(Place $place)
        {
	    return $place->getNom();
	}, $places));

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

    protected function filterEvents(& $events) {
        $uniqs = array_unique($events);
	$filtered = array_filter($uniqs, function(GraphNode $event)
	{
            $lastUpdatedEventTime   = $event->getField('updated_time');
	    $exploration            = $this->firewall->getExploration($event->getField('id'), $this->getSite());
            
            //Connu et (brigand ou non mis à jour) -> on ejecte
            if(null !== $exploration && 
                    ($exploration->getBlackListed() === true || 
                    //Plus grand = plus récent
                    $this->isMoreRecent($lastUpdatedEventTime, $exploration->getLastUpdated()))) {
                return false;
            }
            
            //Lieu déjà connu pour être moisi -> on ejecte
            $place = $event->getField('place');
            if($place && $place->getField('id')) {
                $placeId = $place->getField('id');
                $explorationPlace = $this->firewall->getExploration($placeId, $this->getSite());
                if(null !== $explorationPlace && $explorationPlace->getBlackListed() === true)
                {
                    return false;
                }
            }
            
            //Pas connu des services de police -> présumé innocent              
            return true;    
        });

	return array_map(function(GraphNode $event)
	{
	    return $event->getField('id');
	}, $filtered);
    }

    public function isTrustedLocation() {
	return false; //On ne connait pas ici le lieu réel de l'événement qui peut se situer n'importe où dans le monde
    }


    private function isMoreRecent(\DateTime $date1 = null, \DateTime $date2 = null) {
	if(! $date1 || ! $date2) //Non permissif
	{
	    return false;
	}

	return $date1 > $date2;
    }

    /**
     * Retourne les informations d'un événement en fonction de l'ID de cet événement sur Facebook
     * @param $event
     * @return array l'agenda parsé
     */
    public function getInfoAgenda(GraphNode $event) {

	$tab_retour			    = [];
	
	$tab_retour['nom']		    = $event->getField('name');
	$tab_retour['facebook_event_id']    = $event->getField('id');
	$tab_retour['descriptif']	    = nl2br($event->getField('description'));
	$tab_retour['date_debut']	    = $event->getField('start_time');
	$tab_retour['date_fin']		    = $event->getField('end_time');
	$tab_retour['fb_date_modification'] = $event->getField('updated_time');
	$tab_retour['fb_participations']    = $event->getField('attending_count');
	$tab_retour['fb_interets']	    = $event->getField('maybe_count');

	//Horaires
        $dateDebut	= $tab_retour['date_debut'];
        $dateFin	= $tab_retour['date_fin'];
        $horaires	= null;

        if ($dateDebut instanceof \DateTime && $dateFin instanceof \DateTime) {
            $horaires = sprintf('De %s à %s', $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
        } elseif($dateDebut instanceof \DateTime) {
            $horaires = sprintf("A %s", $dateDebut->format("H\hi"));
        }

        $tab_retour['horaires'] = $horaires;
	        
        //Image
	$tab_retour['url']		    = $this->ensureGoodValue($this->api->getPagePictureURL($event));
        
        //Reservations
	$tab_retour['reservation_internet'] = $this->ensureGoodValue($event->getField('ticket_uri'));

	//Place
	$place = $event->getField('place');
	if ($place) {

	    $tab_retour['place.nom']            = $place->getField('name');
	    $tab_retour['place.facebookId']     = $place->getField('id');

	    //Location
	    $location		    = $place->getField('location');
	    if($location)
	    {
		$tab_retour['place.rue']		= $this->ensureGoodValue($location->getField('street'));
		$tab_retour['place.latitude']		= $this->ensureGoodValue($location->getField('latitude'));
		$tab_retour['place.longitude']		= $this->ensureGoodValue($location->getField('longitude'));
		$tab_retour['place.code_postal']	= $this->ensureGoodValue($location->getField('zip'));
		$tab_retour['place.ville']		= $this->ensureGoodValue($location->getField('city'));
	    }	    
	}

	//Propriétaire de l'événement
	$owner = $event->getField('owner');
	if ($owner) {
	    $tab_retour['facebook_owner_id'] = $owner->getField('id');
            $tab_retour['reservation_internet']	= $this->ensureGoodValue($owner->getField('website'));
            $tab_retour['reservation_telephone']= $this->ensureGoodValue($owner->getField("phone"));
            $fbCategory = $this->ensureGoodValue($owner->getField("category"));
            list($categorie, $type) = $this->guessTypeEventFromCategory($fbCategory);
            $tab_retour['categorie_manifestation'] = $categorie;
            $tab_retour['type_manifestation'] = $type;
            $tab_retour['theme_manifestation'] = $fbCategory;
	}

	return $tab_retour;
    }
    
    private function guessTypeEventFromCategory($category) {
        $list = [
            'Album' => ['type' => 'Musique', 'categorie' => ''],
            'Arts' => ['type' => 'Art', 'categorie' => ''],
            'Athlete' => ['type' => 'Sport', 'categorie' => ''],
            'Artist' => ['type' => 'Concert', 'categorie' => ''],
            'Bar' => ['type' => 'Soirée', 'categorie' => 'Bar'],
            'Cafe' => ['type' => 'Café', 'categorie' => ''],
            'Club' => ['type' => 'Soirée', 'categorie' => 'Boîte de nuit'],
            'Comedian' => ['type' => 'Spectacle', 'categorie' => 'Comédie'],
            'Concert' => ['type' => 'Concert', 'categorie' => ''],
            'Just For Fun' => ['type' => 'Détente', 'categorie' => ''],
            'Gallery' => ['type' => 'Art', 'categorie' => 'Galerie'],
            'Groove' => ['type' => 'Musique', 'categorie' => ''],
            'Library' => ['type' => 'Culture', 'categorie' => ''],
            'Museum' => ['type' => 'Culture', 'categorie' => 'Musée'],
            'Music' => ['type' => 'Musique', 'categorie' => ''],
            'Night' => ['type' => 'Soirée', 'categorie' => 'Boîte de nuit'],  
            'Political' => ['type' => 'Politique', 'categorie' => ''],
            'Record Label' => ['type' => 'Musique', 'categorie' => ''],
            'Restaurant' => ['type' => 'Restaurant', 'categorie' => ''],
            'Sport' => ['type' => 'Art', 'categorie' => ''],
            'Travel' => ['type' => 'Culture', 'categorie' => ''],
            'University' => ['type' => 'Etudiant', 'categorie' => ''],
        ];
        
        $types = [];
        $categories = [];
        foreach($list as $subStr => $group)
        {
            if(false !== strstr($subStr, $category)) {
                $types[] = $group['type'];
                $categories[] = $group['categorie'];
            }
        }
        
        return [implode(',', $types), implode(',', $categories)];
    }
    
    private function ensureGoodValue($value)
    {
        return $value !== '<<not-applicable>>' ? $value : null;
    }
    
    public function getNomData() {
	return 'Facebook';
    }
}
