<?php

namespace TBN\MajDataBundle\Parser;

use TBN\AgendaBundle\Repository\AgendaRepository;
use TBN\SocialBundle\Social\Facebook;
use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Entity\Agenda;

/**
 *
 * @author Guillaume SAINTHILLIER
 */
class FaceBookParser extends AgendaParser {

    /**
     * @var Facebook $api
     */
    protected $api;

    /**
     * @var Site
     */
    protected $site;

    /**
     *
     * @param \TBN\AgendaBundle\Repository\AgendaRepository $repo
     * @param type $api le service qui gère le client FB
     * @param type $site le site courant
     * @return \TBN\MajDataBundle\Parser\FaceBookParser
     */
    public function __construct(AgendaRepository $repo, $api, Site $site, $geocoder) {
	parent::__construct($repo, null);
	$this->api = $api;
	$this->site = $site;
	$this->geocoder = $geocoder;
    }

    public function parse() {
	$agendas    = [];
	$now	    = new \DateTime;
	$keywords   = array_unique(array_merge([$this->site->getNom()],array_map(function(Agenda $agenda) {
	    return $agenda->getLieuNom();
	}, $this->repo->getLieux($this->site))));

	//Récupération des événements depuis le nom des lieux déjà connus
	$events = $this->filterEvents($this->api->searchEventsFromKeywords($keywords, $this->site->getInfo(), $now));

	//Récupération d'ID uniques de créateurs d'événements précédemment parsés
	$owners_id = array_map(function(Agenda $agenda) {
	    return $agenda->getFacebookOwnerId();
	}, $this->repo->getFBOwners($this->site));

	//Tri du tableau pour ajouter les ID des owners des événements parsés
	$filetered_owners_id = array_unique(array_filter($owners_id + array_map(function($event) {
			    $owner = $event->getProperty("owner");
			    return $owner ? $owner->getProperty("id") : null;
			}, $events), function($id) {
		    return $id !== null;
		}));

	//Récupération des événements depuis les créateurs des événements parsés
	$graph = $this->api->searchEventsFromOwnerIds($filetered_owners_id, $this->site->getInfo(), $now);

	if ($graph->getProperty("error_code")) {
	    var_dump("erreur");
	} else {
	    $real_owner_ids = $graph->getPropertyNames();
	    foreach ($real_owner_ids as $id) {
		$owner_events = $graph->getProperty($id);
		$events += $this->filterEvents($owner_events->getPropertyAsArray("data"));
	    }
	}

	//Filtrage des évenements
	foreach ($events as $event) {
	    $filtered_event = $this->getInfoAgenda($event);
	    if ($filtered_event !== null) {
		$agendas[] = $this->hydraterAgenda($filtered_event);
	    }
	}

	return $agendas;
    }

    protected function filterEvents($events) {
	$filtered_events = [];
	foreach ($events as $event) {
	    $filtered_events[$event->getProperty("id")] = $event;
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
	    $venue = $this->api->getPageFromId($this->site->getInfo(), $page->getProperty("id"));

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
     * @return array l'agenda parsé
     */
    public function getInfoAgenda(\Facebook\GraphObject $event) {
	$tab_retour = [];

	$name = $event->getProperty("name");
	$description = $event->getProperty("description");
	$location = $event->getProperty("location");
	$start_time = $this->getDateFromEvent($event, "start_time");

	if (!$name or ! $description or ! $location or ! $start_time) {
	    return null;
	}

	$tab_retour["nom"] = $name;
	$tab_retour["fb_id"] = $event->getProperty("id");
	$tab_retour["descriptif"] = nl2br($description);
	$tab_retour["lieu_nom"] = $location;
	$tab_retour["date_debut"] = $start_time;
	$tab_retour["date_fin"] = $this->getDateFromEvent($event, "end_time");

	//Récupération des horaires
	if (!$event->getProperty("is_date_only")) {
	    $horaires = "";
	    $dateDebut = $tab_retour["date_debut"];
	    $dateFin = $tab_retour["date_fin"];
	    if ($dateFin and $this->isSameDay($dateDebut, $dateFin)) {
		$horaires = sprintf("De %s à %s", $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
	    } else {
		$horaires = sprintf("A %s", $dateDebut->format("H\hi"));
	    }
	    $tab_retour["horaires"] = $horaires;
	}

	$tab_retour["image"] = $this->api->getPagePicture($event);
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

	if (!$latitude and ! $longitude and $location) { //Si rien de précis n'est renseigné, on tente le geocoding
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

	if ($latitude === null or ( $latitude !== null and abs($this->site->getLatitude() - $latitude) > $distanceMax)) {
	    return null;
	}

	if ($longitude === null or ( $longitude !== null and abs($this->site->getLongitude() - $longitude) > $distanceMax)) {
	    return null;
	}

	$owner = $event->getProperty("owner");
	if ($owner) {
	    $tab_retour["owner"] = $owner->getProperty("id");
	}

	return $tab_retour;
    }

    public function hydraterAgenda($event) {

	$nom = $event["nom"];
	$dateDebut = $event["date_debut"];

	$a = $this->getAgendaFromUniqueInfo($nom, $dateDebut);

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
