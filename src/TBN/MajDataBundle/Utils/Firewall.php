<?php

namespace TBN\MajDataBundle\Utils;

use Doctrine\Bundle\DoctrineBundle\Registry;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Geolocalize\BoundaryInterface;
use TBN\AgendaBundle\Geolocalize\GeolocalizeInterface;
use TBN\MajDataBundle\Entity\Exploration;
use TBN\MajDataBundle\Reject\Reject;
use TBN\MajDataBundle\Repository\ExplorationRepository;
use TBN\UserBundle\Entity\User;

/**
 * Description of Firewall.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Firewall
{
    const VERSION = '1.1';

    protected $toSaveExplorations;
    protected $explorations;
    protected $fbExploration;
    protected $comparator;
    protected $om;

    /**
     * @var ExplorationRepository
     */
    protected $repoExploration;

    public function __construct(Registry $doctrine, Comparator $comparator)
    {
        $this->om              = $doctrine->getManager();
        $this->repoExploration = $this->om->getRepository('TBNMajDataBundle:Exploration');
        $this->comparator      = $comparator;
        $this->places          = [];
        $this->explorations    = [];
    }

    public function loadExplorations(array $ids)
    {
        $explorations = $this->repoExploration->findAllByFBIds($ids);
        foreach ($explorations as $exploration) {
            $this->addExploration($exploration);
        }
    }

    protected function hasExplorationToBeUpdated(Exploration $exploration)
    {
        if (self::VERSION !== $exploration->getFirewallVersion()) {
            return true;
        }

        return false;
    }

    public function hasPlaceToBeUpdated(Exploration $exploration)
    {
        return $this->hasExplorationToBeUpdated($exploration);
    }

    public function hasEventToBeUpdated(Exploration $exploration, Agenda $event)
    {
        $explorationDate       = $exploration->getLastUpdated();
        $eventDateModification = $event->getFbDateModification();

        if (!$explorationDate || !$eventDateModification) {
            return true;
        }

        if ($eventDateModification > $explorationDate) {
            return true;
        }

        return false;
    }

    public function isValid(Agenda $event)
    {
        return $event->getReject()->isValid() && $event->getPlace()->getReject()->isValid();
    }

    public function isPersisted($object)
    {
        return null !== $object && null !== $object->getId();
    }

    public function filterEventIntegrity(Agenda $event, User $oldEventUser = null)
    {
        if (!$oldEventUser) {
            return;
        }

        if (null === $event->getUser() ||
            ($event->getUser()->getId() !== $oldEventUser->getId())) {
            $event->getReject()->addReason(Reject::BAD_USER);
        }
    }

    public function filterEvent(Agenda $event)
    {
        $this->filterEventInfos($event);
        if ($event->getPlace()) {
            $this->filterEventPlace($event->getPlace());
        }
    }

    public function filterEventSite(Agenda $event)
    {
        if (!$event->getSite()) {
            $event->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
            $event->getPlace()->getReject()->addReason(Reject::BAD_PLACE_LOCATION);
        }
    }

    public function filterEventExploration(Exploration $exploration, Agenda $event)
    {
        $reject = $exploration->getReject();

        //Aucune action sur un événement supprimé sur la plateforme par son créateur
        if ($reject->isEventDeleted()) {
            return;
        }

        $hasFirewallVersionChanged = $this->hasExplorationToBeUpdated($exploration);
        $hasToBeUpdated            = $this->hasEventToBeUpdated($exploration, $event);

        //L'évémenement n'a pas changé -> non valide
        if (!$hasToBeUpdated && !$reject->hasNoNeedToUpdate()) {
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
            //L'événement a changé -> valide
        } elseif ($hasToBeUpdated && $reject->hasNoNeedToUpdate()) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        //L'exploration est ancienne -> maj de la version
        if ($hasFirewallVersionChanged) {
            $exploration->setFirewallVersion(self::VERSION);

            //L'événement n'était pas valide -> valide
            if (!$reject->hasNoNeedToUpdate()) {
                $reject->setValid();
            }
        }
    }

    protected function filterEventPlace(Place $place)
    {
        if (!$this->checkMinLengthValidity($place->getNom(), 2)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_NAME);
        }

        if (!$this->checkMinLengthValidity($place->getVille(), 2) && (
            !$place->getLatitude() ||
            !$place->getLongitude()
        )) {
            $place->getReject()->addReason(Reject::NO_PLACE_LOCATION_PROVIDED);
        }

        $codePostal = $this->comparator->sanitizeNumber($place->getCodePostal());
        if (!$this->checkLengthValidity($codePostal, 0) && !$this->checkLengthValidity($codePostal, 5)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
        }

        //Observation du lieu
        if ($place->getFacebookId()) {
            $exploration = $this->getExploration($place->getFacebookId());
            if (!$exploration) {
                $exploration = (new Exploration())
                    ->setId($place->getFacebookId())
                    ->setReject($place->getReject())
                    ->setReason($place->getReject()->getReason())
                    ->setFirewallVersion(self::VERSION);
                $this->addExploration($exploration);
            } else {
                $exploration
                    ->setReject($place->getReject())
                    ->setReason($place->getReject()->getReason());
            }
        }
    }

    protected function filterEventInfos(Agenda $event)
    {
        if (!$this->checkMinLengthValidity($event->getNom(), 3)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_NAME);
        }

        if (!$this->checkMinLengthValidity($event->getDescriptif(), 20)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DESCRIPTION);
        }

        if ($this->isSPAMContent($event->getDescriptif())) {
            $event->getReject()->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        if (!$event->getDateDebut() instanceof \DateTime ||
            ($event->getDateFin() && !$event->getDateFin() instanceof \DateTime)
        ) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE);
        } elseif ($event->getDateFin() && $event->getDateFin() < $event->getDateDebut()) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
        }

        if (!$event->getPlace()) {
            $event->getReject()->addReason(Reject::NO_PLACE_PROVIDED);
        }

        //Observation de l'événément
        if ($event->getFacebookEventId()) {
            $exploration = $this->getExploration($event->getFacebookEventId());
            if (!$exploration) {
                $exploration = (new Exploration())
                    ->setId($event->getFacebookEventId())
                    ->setLastUpdated($event->getFbDateModification())
                    ->setReject($event->getReject())
                    ->setReason($event->getReject()->getReason())
                    ->setFirewallVersion(self::VERSION);

                $this->addExploration($exploration);
            } else {
                //Pas besoin de paniquer l'EM si les dates sont équivalentes
                if ($exploration->getLastUpdated() != $event->getFbDateModification()) {
                    $exploration->setLastUpdated($event->getFbDateModification());
                }

                $exploration
                    ->setReject($event->getReject())
                    ->setReason($event->getReject()->getReason());
            }
        }
    }

    public function isPreciseLocation(GeolocalizeInterface $entity)
    {
        return $entity->getLatitude() && $entity->getLongitude();
    }

    public function isLocationBounded(GeolocalizeInterface $entity, BoundaryInterface $boundary)
    {
        return $this->isPreciseLocation($entity) &&
        $this->isPreciseLocation($boundary) &&
        $this->distance($entity, $boundary) <= $boundary->getDistanceMax();
    }

    private function distance(GeolocalizeInterface $entity, BoundaryInterface $boundary)
    {
        $theta = $entity->getLongitude() - $boundary->getLongitude();
        $dist  = sin(deg2rad($entity->getLatitude())) *
            sin(deg2rad($boundary->getLatitude())) +
                cos(deg2rad($entity->getLatitude())) *
                cos(deg2rad($boundary->getLatitude())) *
                cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);

        return $dist * 111.189577; //60 * 1.1515 * 1.609344
    }

    public function deleteCache()
    {
        $this->comparator->deleteCache();
    }

    /**
     * @param int $fbId
     *
     * @return Exploration|null
     */
    public function getExploration($fbId)
    {
        if (!isset($this->explorations[$fbId])) {
            return;
        }

        return $this->explorations[$fbId];
    }

    private function isSPAMContent($content)
    {
        $black_list = [
            'Buy && sell tickets at', 'Please join', 'Invite Friends', 'Buy Tickets',
            'Find Local Concerts', 'reverbnation.com', 'pastaparty.com', 'evrd.us',
            'farishams.com', 'tinyurl.com', 'bandcamp.com', 'ty-segall.com',
            'fritzkalkbrenner.com', 'campusfm.fr', 'polyamour.info', 'parislanuit.fr',
            'Please find the agenda', 'Fore More Details like our Page & Massage us',
        ];

        $filter = array_filter($black_list, function ($elem) use ($content) {
            return strstr($content, $elem);
        });

        return count($filter) > 0;
    }

    public function getVilleHash($villeName)
    {
        return $this->comparator->sanitizeVille($villeName);
    }

    private function checkLengthValidity($str, $length)
    {
        return strlen($this->comparator->sanitize($str)) === $length;
    }

    public function checkMinLengthValidity($str, $min)
    {
        return isset($this->comparator->sanitize($str)[$min]);
    }

    public function addExploration(Exploration $exploration)
    {
        $reject = new Reject();
        $reject->setReason($exploration->getReason());

        $this->explorations[$exploration->getId()] = $exploration->setReject($reject);
    }

    public function getExplorations()
    {
        return $this->explorations;
    }

    public function flushExplorations()
    {
        unset($this->explorations);
        $this->explorations = [];
    }
}
