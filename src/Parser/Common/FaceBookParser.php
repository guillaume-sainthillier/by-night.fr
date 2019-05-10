<?php

namespace App\Parser\Common;

use App\Entity\Agenda;
use App\Entity\City;
use App\Entity\Place;
use App\Parser\AgendaParser;
use App\Repository\SiteRepository;
use App\Social\FacebookAdmin;
use App\Utils\Firewall;
use App\Utils\Monitor;
use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use Facebook\GraphNodes\GraphNode;

/**
 * Classe de parsing des événements FB.
 *
 * @author Guillaume SAINTHILLIER
 */
class FaceBookParser extends AgendaParser
{
    /**
     * @var FacebookAdmin
     */
    protected $api;

    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var SiteRepository
     */
    protected $repoSite;

    /**
     * @var Firewall
     */
    protected $firewall;

    public function __construct(ObjectManager $om, Firewall $firewall, FacebookAdmin $api)
    {
        $this->firewall = $firewall;
        $this->api = $api;
        $this->om = $om;
    }

    protected function getPlaces()
    {
        $places = $this->om->getRepository(Place::class)->findAllFBIds();

        return $places;
    }

    protected function getUsers()
    {
        $users = $this->om->getRepository(Agenda::class)->findAllFBOwnerIds();

        return $users;
    }

    protected function getCities()
    {
        return $this->om->getRepository(City::class)->findAllCities();
    }

    protected function getSiteLocations()
    {
        return $this->om->getRepository(City::class)->findLocations();
    }

    protected function getEventsFromUsers(array $additional_users, DateTime $now)
    {
        $users = $this->getUsers();
        $users = \array_unique(\array_merge($users, $additional_users));

        //Récupération des événements depuis les lieux trouvés
        Monitor::writeln('Recherche des événements associés aux users ...');
        $events = $this->api->getEventsFromUsers($users, $now);
        Monitor::writeln(\sprintf(
            '<info>%d</info> événements trouvés',
            \count($events)
        ));

        return $events;
    }

    protected function getEventsFromPlaces(DateTime $now)
    {
        $places = $this->getPlaces();

        //Récupération des places depuis les GPS
        $locations = $this->getSiteLocations();
        Monitor::writeln('Recherche des places associés aux sites ...');
        $gps_places = $this->api->getPlacesFromGPS($locations);
        Monitor::writeln(\sprintf(
            '<info>%d</info> places trouvées',
            \count($gps_places)
        ));

        $gps_places = \array_map(function (GraphNode $node) {
            return $node->getField('id');
        }, $gps_places);

        $places = \array_unique(\array_filter(\array_merge($places, $gps_places)));

        //        Récupération des événements depuis les lieux trouvés
        Monitor::writeln('Recherche des événements associés aux places ...');
        $events = $this->api->getEventsFromPlaces($places, $now);
        Monitor::writeln(\sprintf(
            '<info>%d</info> événements trouvés',
            \count($events)
        ));

        return $events;
    }

    protected function getEventFromCities(DateTime $now)
    {
        //Récupération des événements par mots-clés
        Monitor::writeln("Recherche d'événements associés aux mots clés...");
        $cities = $this->getCities();
        \shuffle($cities);
        $cities = \array_slice($cities, 0, 100);
        $events = $this->api->getEventsFromKeywords($cities, $now);
        Monitor::writeln(\sprintf(
            '<info>%d</info> événements trouvés',
            \count($events)
        ));

        return $events;
    }

    protected function getOwners(array $nodes)
    {
        return \array_filter(\array_map(function (GraphNode $node) {
            $owner = $node->getField('owner');

            return $owner ? $owner->getField('id') : null;
        }, $nodes));
    }

    public function getRawAgendas()
    {
        $now = new DateTime();

//        $events = $this->api->getEventsFromIds(["830234333792674", "1538235536480501"]);
//        $events = $this->api->getEventsFromIds(['1752921201640362', '1538235536480501', '248556222222718']);

//        return array_map([$this, 'getInfoAgenda'], $events);

        //Recherche d'événements de l'API en fonction des lieux
        $place_events = $this->getEventsFromPlaces($now);
        $place_users = $this->getOwners($place_events);

        //Recherche d'événements de l'API en fonction des users
        $user_events = $this->getEventsFromUsers($place_users, $now);

        //Recherche d'événements de l'API en fonction des villes
        $cities_events = $this->getEventFromCities($now);

        $events = $this->getUniqueEvents(\array_merge($place_events, $user_events, $cities_events));
        Monitor::writeln(\sprintf(
            '<info>%d</info> événement(s) à traiter au total',
            \count($events)
        ));

        //Appel au GC
        unset($place_events, $user_events, $cities_events);

        return \array_map([$this, 'getInfoAgenda'], $events);
    }

    public function getIdsToMigrate()
    {
        return $this->api->getIdsToMigrate();
    }

    protected function getUniqueEvents(array $events)
    {
        $uniqueEvents = [];
        foreach ($events as $event) {
            $uniqueEvents[$event->getField('id')] = $event;
        }

        return $uniqueEvents;
    }

    /**
     * Retourne les informations d'un événement en fonction de l'ID de cet événement sur Facebook.
     *
     * @param $event
     *
     * @return array l'agenda parsé
     */
    public function getInfoAgenda(GraphNode $event)
    {
        $tab_retour = [];

        $tab_retour['nom'] = $event->getField('name');
        $tab_retour['facebook_event_id'] = $event->getField('id');
        $tab_retour['external_id'] = 'FB-' . $event->getField('id');
        $tab_retour['descriptif'] = \nl2br($event->getField('description'));
        $tab_retour['date_debut'] = $event->getField('start_time');
        $tab_retour['date_fin'] = $event->getField('end_time');
        $tab_retour['fb_date_modification'] = $event->getField('updated_time');
        $tab_retour['fb_participations'] = $event->getField('attending_count');
        $tab_retour['fb_interets'] = $event->getField('maybe_count');

        //Horaires
        $dateDebut = $tab_retour['date_debut'];
        $dateFin = $tab_retour['date_fin'];
        $horaires = null;

        if ($dateDebut instanceof DateTime && $dateFin instanceof DateTime) {
            $horaires = \sprintf('De %s à %s', $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
        } elseif ($dateDebut instanceof DateTime) {
            $horaires = \sprintf('A %s', $dateDebut->format("H\hi"));
        }

        $tab_retour['horaires'] = $horaires;

        //Image
        $tab_retour['url'] = $this->api->ensureGoodValue($this->api->getPagePictureURL($event));

        //Reservations
        $tab_retour['reservation_internet'] = $this->api->ensureGoodValue($event->getField('ticket_uri'));

        //Place
        $place = $event->getField('place');
        if ($place) {
            $tab_retour['placeName'] = $place->getField('name');
            if ($place->getField('id')) {
                $tab_retour['placeExternalId'] = 'FB-' . $place->getField('id');
            }

            //Location
            $location = $place->getField('location');
            if ($location) {
                $tab_retour['placeStreet'] = $this->api->ensureGoodValue($location->getField('street'));
                $tab_retour['latitude'] = $this->api->ensureGoodValue($location->getField('latitude'));
                $tab_retour['longitude'] = $this->api->ensureGoodValue($location->getField('longitude'));
                $tab_retour['placePostalCode'] = $this->api->ensureGoodValue($location->getField('zip'));
                $tab_retour['placeCity'] = $this->api->ensureGoodValue($location->getField('city'));
                $tab_retour['placeCountryName'] = $this->api->ensureGoodValue($location->getField('country'));
            } else {
                $placeName = $place->getField('name');
                $parts = array_map('trim', explode(',', $placeName));
                if(count($parts) === 3) {
                    $tab_retour['placeCountryName'] = $parts[2];
                    $cityAndPostalCode = explode(' ', $parts[1]);
                    if(count($cityAndPostalCode) > 1) {
                        $tab_retour['placePostalCode'] = $cityAndPostalCode[0];
                        unset($cityAndPostalCode[0]);
                        $tab_retour['placeCity'] = implode(' ', $cityAndPostalCode);
                    }
                    $tab_retour['placeStreet'] = $parts[0];
                }
            }
        }

        //Propriétaire de l'événement
        $owner = $event->getField('owner');
        if ($owner) {
            $tab_retour['facebook_owner_id'] = $owner->getField('id');
            $tab_retour['reservation_internet'] = $this->api->ensureGoodValue($owner->getField('website'));
            $tab_retour['reservation_telephone'] = $this->api->ensureGoodValue($owner->getField('phone'));
            $fbCategory = $this->api->ensureGoodValue($owner->getField('category'));
            list($categorie, $type) = $this->guessTypeEventFromCategory($fbCategory);
            $tab_retour['categorie_manifestation'] = $categorie;
            $tab_retour['type_manifestation'] = $type;
        }

        return $tab_retour;
    }

    private function guessTypeEventFromCategory($category)
    {
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
        foreach ($list as $subStr => $group) {
            if (false !== \strstr($category, $subStr)) {
                $types[] = $group['type'];
                $categories[] = $group['categorie'];
            }
        }

        return [\implode(',', $types), \implode(',', $categories)];
    }

    public function getNomData()
    {
        return 'Facebook';
    }
}
