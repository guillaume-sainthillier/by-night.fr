<?php

namespace App\Utils;

use App\Entity\Place;
use App\Entity\Agenda;
use App\Geolocalize\BoundaryInterface;
use App\Geolocalize\GeolocalizeInterface;
use App\Entity\Exploration;
use App\Reject\Reject;
use App\Repository\ExplorationRepository;
use App\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;

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

    /**
     * @var Comparator
     */
    protected $comparator;

    /**
     * @var \Doctrine\Common\Persistence\ObjectManager|object
     */
    protected $om;

    /**
     * @var ExplorationRepository
     */
    protected $repoExploration;

    public function __construct(ObjectManager $om, Comparator $comparator)
    {
        $this->om              = $om;
        $this->repoExploration = $this->om->getRepository(Exploration::class);
        $this->comparator      = $comparator;
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
            ($event->getUser()->getId() !== $oldEventUser->getId())
        ) {
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

    public function filterEventLocation(Agenda $event)
    {
        $place  = $event->getPlace();
        $reject = $place->getReject();
        if (!$reject->isValid()) {
            $event->getReject()->addReason($reject->getReason());
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
        //Le nom du lieu doit comporter au moins 2 caractères
        if (!$this->checkMinLengthValidity($place->getNom(), 2)) {
            $place->getReject()->addReason(Reject::BAD_PLACE_NAME);
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

    /**
     * @param Agenda $event
     */
    protected function filterEventInfos(Agenda $event)
    {
        //Le nom de l'événement doit comporter au moins 3 caractères
        if (!$this->checkMinLengthValidity($event->getNom(), 3)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_NAME);
        }

        //La description de l'événment doit comporter au moins 20 caractères
        if (!$this->checkMinLengthValidity($event->getDescriptif(), 20)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DESCRIPTION);
        }

        //Pas de SPAM dans la description
        if ($this->isSPAMContent($event->getDescriptif())) {
            $event->getReject()->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        //Pas de dates valides fournies
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
                if ($exploration->getLastUpdated() !== $event->getFbDateModification()) {
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
        $dist  = \sin(\deg2rad($entity->getLatitude())) *
            \sin(\deg2rad($boundary->getLatitude())) +
            \cos(\deg2rad($entity->getLatitude())) *
            \cos(\deg2rad($boundary->getLatitude())) *
            \cos(\deg2rad($theta));
        $dist = \acos($dist);
        $dist = \rad2deg($dist);

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
            return null;
        }

        return $this->explorations[$fbId];
    }

    private function isSPAMContent($content)
    {
        $black_list = [
            'Buy && sell tickets at', 'Please join', 'Invite Friends', 'Buy Tickets',
            'Find Local Concerts', 'reverbnation.com', 'pastaparty.com', 'evrd.us',
            'farishams.com', 'ty-segall.com',
            'fritzkalkbrenner.com', 'campusfm.fr', 'polyamour.info', 'parislanuit.fr',
            'Please find the agenda', 'Fore More Details like our Page & Massage us',
        ];

        foreach ($black_list as $black_word) {
            if (\strstr($content, $black_word)) {
                return true;
            }
        }

        return false;
    }

    public function getVilleHash($villeName)
    {
        return $this->comparator->sanitizeVille($villeName);
    }

    private function checkLengthValidity($str, $length)
    {
        return \strlen($this->comparator->sanitize($str)) === $length;
    }

    public function checkMinLengthValidity($str, $min)
    {
        return isset(\trim($str)[$min]);
    }

    public function addExploration(Exploration $exploration)
    {
        $reject = new Reject();
        $reject->setReason($exploration->getReason());

        $this->explorations[$exploration->getId()] = $exploration->setReject($reject);
    }

    /**
     * @return Exploration[]
     */
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
