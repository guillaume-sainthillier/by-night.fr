<?php

namespace TBN\MajDataBundle\Parser;

use Symfony\Component\Console\Output\OutputInterface;

use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\SocialBundle\Social\FacebookAdmin;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Entity\BlackList;
use TBN\MajDataBundle\Entity\BlackListRepository;
use TBN\UserBundle\Entity\SiteInfo;


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
     * @var Site
     */
    protected $site;
    
    /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     *
     * @var BlackListRepository
     */
    protected $repoBlackList;

    /**
     *
     * @var Geocoder
     */
    protected $geocoder;


    /**
     *
     * @param \TBN\AgendaBundle\Repository\AgendaRepository $repo
     * @param type $api le service qui gère le client FB
     * @param type $site le site courant
     * @return \TBN\MajDataBundle\Parser\FaceBookParser
     */
    public function __construct(AgendaRepository $repo, BlackListRepository $repoBlackList, FacebookAdmin $api, Site $site, SiteInfo $siteInfo, $geocoder) {
	parent::__construct($repo, null);
        
        $this->repoBlackList    = $repoBlackList;
	$this->api              = $api->setSiteInfo($siteInfo);
	$this->site             = $site;
        $this->siteInfo         = $siteInfo;
	$this->geocoder         = $geocoder;
    }

    
    public function parse(OutputInterface $output) {
	$agendas        = [];
        $now            = new \DateTime;
        $place_events   = $this->getEventsFromPlaces($now, $output);

        $this->write($output, 'Recherche des utilisateurs...');
        //Récupération des utilisateurs FB depuis la BD
        $fb_users   = array_map(function(Agenda $agenda)
        {
	    return $agenda->getFacebookOwnerId();
	}, $this->repo->getFBOwners($this->site));

        //Récupération des utilisateurs FB depuis les événements précédemment parsés
        $event_users = array_map(function(\Facebook\GraphObject $event)
        {
            $owner = $event->getProperty("owner");
            return $owner ? $owner->getProperty("id") : null;
        }, $place_events);

        //On ne garde que les événements dont le créateur est un utilisateur FB
        $real_event_users = array_filter($event_users, function($id)
        {
            return $id !== null;
        });

        //Fusion des utilisateurs tirés de la BD et ceux parsés
	$full_users     = array_unique(array_merge($fb_users, $real_event_users));
        $this->writeln($output, '<info>'.count($full_users).'</info> utilisateurs trouvés');

	//Récupération des événements depuis les utilisateurs FB
        $this->write($output, 'Recherche d\'événements associés aux utilisateurs...');
	$user_events    = $this->api->getEventsFromUsers($full_users, $now, $output);
        $this->writeln($output, '<info>'.count($user_events).'</info> événements trouvés');
        
        //Construction de tous les événements
        $events         = array_merge($place_events, $user_events);

        //Filtrage des événements
        $this->write($output, 'Filtrage de tous les événements...');
        $filtered_events = $this->filterEvents($events, $output);
        $this->writeln($output, '<info>'.(count($filtered_events)).'</info> événéments retenus');

	//Création des instances d'Agenda
	foreach ($filtered_events as $event) {
	    $retour = $this->getInfoAgenda($event);
	    if ($retour instanceof BlackList) {
		$this->blackLists[] = $retour;
	    }else
            {
                $agendas[] = $this->hydraterAgenda($retour);
            }
	}
	return $agendas;
    }

    protected function getEventsFromPlaces(\DateTime $now, OutputInterface $output)
    {
        $places     = array_map(function(Agenda $agenda)
        {
	    return trim($agenda->getLieuNom());
	}, $this->repo->getLieux($this->site));

        $this->write($output, "Recherche d'endroits vers [".$this->site->getLatitude()."; ".$this->site->getLongitude()."]...");
        $fb_places      = $this->api->getPlacesFromGPS($this->site->getLatitude(), $this->site->getLongitude(), $this->site->getDistanceMax()* 10000);
        $this->writeln($output, " <info>".(count($fb_places))."</info> endroits trouvés");

        $this->write($output, "Recherche d'événements associés aux endroits...");
        $full_places    = array_unique(array_merge([$this->site->getNom()], $places, $fb_places));
        $events         = $this->api->searchEventsFromKeywords($full_places, $now, $output);
        $this->writeln($output, " <info>".(count($events))."</info> événements trouvés");
        return $events;
    }

    protected function filterEvents($events) {
        $blackLists         = $this->repoBlackList->findBy(["site" => $this->site]);
        $blackListIds       = [];
	$filtered_events    = [];

        foreach($blackLists as $blackList)
        {
            $blackListIds[$blackList->getFacebookId()] = $blackList;
        }

	foreach ($events as $event) {
            $fbId = $event->getProperty("id");
            if(!isset($blackListIds[$fbId]) && !isset($filtered_events[$fbId]))
            {
                $filtered_events[$fbId] = $event;
            }
	}

	return $filtered_events;
    }

    protected function getDateFromEvent($event, $key) {
	$str_date = $event->getProperty($key);
	if ($str_date === null) {
	    return null;
	}

	return \DateTime::createFromFormat($event->getProperty("is_date_only") ? "Y-m-d" : "Y-m-d\TH:i:sP", $str_date);
    }

    protected function isSameDay($date1, $date2) {
	return $date1->format("Y-m-d") === $date2->format("Y-m-d");
    }

    protected function getPageInfos($page) {
	if ($page->getProperty("id")) {
	    $venue = $this->api->getPageFromId($page->getProperty("id"));

	    return [
		"site" => $venue->getProperty("website"),
		"telephone" => $venue->getProperty("phone")
	    ];
	}

	return [];
    }

    /**
     * Retourne les informations d'un événement en fonction de l'ID de cet événement sur Facebook
     * @param type $event
     * @return array|BlackList l'agenda parsé
     */
    public function getInfoAgenda(\Facebook\GraphObject $event) {
        $blackList      = (new BlackList)->setSite($this->site)->setFacebookId($event->getProperty("id"));
	$tab_retour     = [];
	$name           = $event->getProperty("name");
	$description    = $event->getProperty("description");
	$location       = $event->getProperty("location");
	$start_time     = $this->getDateFromEvent($event, "start_time");

	if (!$name || !$description || !$location || !$start_time) {
            return $blackList->setReason("Informations de base non fournies");
	}

	$tab_retour["nom"] = $name;
	$tab_retour["fb_id"] = $event->getProperty("id");
	$tab_retour["descriptif"] = nl2br($description);
	$tab_retour["lieu_nom"] = $location;
	$tab_retour["date_debut"] = $start_time;
	$tab_retour["date_fin"] = $this->getDateFromEvent($event, "end_time");


	//Horaires
        $horaires = null;
	if (!$event->getProperty("is_date_only")) {
	    $dateDebut = $tab_retour["date_debut"];
	    $dateFin = $tab_retour["date_fin"];
	    if ($dateFin && $this->isSameDay($dateDebut, $dateFin)) {
		$horaires = sprintf("De %s à %s", $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
	    } else {
		$horaires = sprintf("A %s", $dateDebut->format("H\hi"));
	    }
	}
        $tab_retour["horaires"] = $horaires;

        //Image
	$tab_retour["image"] = $this->api->getPagePicture($event);
        
        //Reservations
	$tab_retour["reservation_internet"] = $event->getProperty("ticket_uri");

	$latitude = null;
	$longitude = null;
	$rue = null;
	$code_postal = null;
	$ville = null;
	$distanceMax = $this->site->getDistanceMax();

	if ($event->getProperty("venue")) {
	    $venue = $event->getProperty("venue");
	    $tab_retour = array_merge($tab_retour, $this->getPageInfos($venue));

	    $rue = $venue->getProperty("street");
	    $code_postal = $venue->getProperty("zip");
	    $ville = $venue->getProperty("city");
	    $latitude = $venue->getProperty("latitude");
	    $longitude = $venue->getProperty("longitude");
	}

	if (!$latitude && !$longitude && $location) { //Si rien de précis n'est renseigné, on tente le geocoding
	    $geocoder = $this->geocoder;
	    $response = $geocoder->geocode($location);
	    $status = $response->getStatus();

	    if ($status === "OK") {
		$results = $response->getResults();
		foreach ($results as $result) {
		    $location = $result->getGeometry()->getLocation();

		    if (!$result->isPartialMatch()) { //L'adresse a été trouvée précisément
			$numRue = null;
			foreach ($result->getAddressComponents('street_number') as $addressComponent) {
			    $numRue = $addressComponent->getLongName();
			}

			foreach ($result->getAddressComponents('route') as $addressComponent) {
			    $rue = trim($numRue . " " . $addressComponent->getLongName());
			}

			foreach ($result->getAddressComponents('locality') as $addressComponent) {
			    $ville = $addressComponent->getLongName();
			}

			foreach ($result->getAddressComponents('postal_code') as $addressComponent) {
			    $code_postal = $addressComponent->getLongName();
			}
		    }

		    $latitude = $location->getLatitude();
		    $longitude = $location->getLongitude();
		}
	    }
	}

	$tab_retour["rue"] = $rue;
	$tab_retour["code_postal"] = $code_postal;
	$tab_retour["ville"] = $ville;
	$tab_retour["latitude"] = $latitude;
	$tab_retour["longitude"] = $longitude;

	if ($latitude === null || ($latitude !== null && abs($this->site->getLatitude() - $latitude) > $distanceMax)) {
	    return $blackList->setReason("Coordonnées non conformes");
	}

	if ($longitude === null || ($longitude !== null && abs($this->site->getLongitude() - $longitude) > $distanceMax)) {
	    return $blackList->setReason("Coordonnées non conformes");
	}

        $owner_id = null;
	$owner = $event->getProperty("owner");
	if ($owner) {
	    $owner_id = $owner->getProperty("id");
	}
        $tab_retour["owner"] = $owner_id;

	return $tab_retour;
    }
    
    protected function getAgendaFromUniqueInfo($nom, $dateDebut, $dateFin = null, $lieuNom = null, $facebookId = null)
    {
        $agenda = $this->repo->findOneBy([
            "facebookEventId" => $facebookId
        ]);
        
        if($agenda !== null)
        {
            return $agenda;
        }
        
        return parent::getAgendaFromUniqueInfo($nom, $dateDebut, $dateFin, $lieuNom);
    }

    public function hydraterAgenda($event) {

	$nom = $event["nom"];
	$dateDebut = $event["date_debut"];

	$a = $this->getAgendaFromUniqueInfo($nom, $dateDebut, null, null, $event["fb_id"]);

	$a->setNom($nom);
	$a->setFacebookEventId($event["fb_id"]);
	$a->setDescriptif($event["descriptif"]);
	$a->setDateDebut($dateDebut);

	if (isset($event["horaires"])) {
	    $a->setHoraires($event["horaires"]);
	}

	if (isset($event["date_fin"])) {
	    $a->setDateFin($event["date_fin"]);
	}

	if (isset($event["lieu_nom"])) {
	    $a->setLieuNom($event["lieu_nom"]);
	}

	if (isset($event["reservation_internet"])) {
	    $a->setReservationInternet($event["reservation_internet"]);
	}

	if (isset($event["commune"])) {
	    $a->setCommune($event["commune"]);
	}

	if (isset($event["ville"])) {
	    $a->setVille($event["ville"]);
	}

	if (isset($event["code_postal"])) {
	    $a->setCodePostal($event["code_postal"]);
	}

	if (isset($event["rue"])) {
	    $a->setRue($event["rue"]);
	}

	if (isset($event["latitude"])) {
	    $a->setLatitude($event["latitude"]);
	}

	if (isset($event["image"])) {
	    $a->setUrl($event["image"]);
	}

	if (isset($event["longitude"])) {
	    $a->setLongitude($event["longitude"]);
	}

	if (isset($event["site"])) {
	    $a->setReservationInternet($event["site"]);
	}

	if (isset($event["telephone"])) {
	    $a->setReservationTelephone($event["telephone"]);
	}

	if (isset($event["owner"])) {
	    $a->setFacebookOwnerId($event["owner"]);
	}

	return $a;
    }

    public function getNomData() {
	return "Facebook";
    }
}
