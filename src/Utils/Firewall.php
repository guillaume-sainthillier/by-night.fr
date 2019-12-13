<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\Event;
use App\Entity\Exploration;
use App\Reject\Reject;
use Doctrine\ORM\EntityManagerInterface;

class Firewall
{
    const VERSION = '1.1';

    private $explorations;

    /**
     * @var Comparator
     */
    private $comparator;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em, Comparator $comparator)
    {
        $this->em = $em;
        $this->comparator = $comparator;
        $this->explorations = [];
    }

    public function loadExplorations(array $ids)
    {
        $explorations = $this->em->getRepository(Exploration::class)->findBy([
            'externalId' => $ids,
        ]);

        foreach ($explorations as $exploration) {
            $this->addExploration($exploration);
        }
    }

    public function addExploration(Exploration $exploration)
    {
        $reject = new Reject();
        $reject->setReason($exploration->getReason());

        $this->explorations[$exploration->getExternalId()] = $exploration->setReject($reject);
    }

    public function hasPlaceToBeUpdated(Exploration $exploration, Event $event)
    {
        return $this->hasExplorationToBeUpdated($exploration, $event);
    }

    private function hasExplorationToBeUpdated(Exploration $exploration, Event $event)
    {
        if (self::VERSION !== $exploration->getFirewallVersion() || $event->getParserVersion() !== $exploration->getFirewallVersion()) {
            return true;
        }

        return false;
    }

    public function isValid(Event $event)
    {
        return $event->getReject()->isValid() && $event->getPlaceReject()->isValid();
    }

    public function filterEvent(Event $event)
    {
        $this->filterEventInfos($event);
        $this->filterEventPlace($event);
    }

    private function filterEventInfos(Event $event)
    {
        //Le nom de l'événement doit comporter au moins 3 caractères
        if (!$event->isAffiliate() && !$this->checkMinLengthValidity($event->getNom(), 3)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_NAME);
        }

        //La description de l'événement doit comporter au moins 20 caractères
        if (!$event->isAffiliate() && !$this->checkMinLengthValidity($event->getDescriptif(), 10)) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DESCRIPTION);
        }

        //Pas de SPAM dans la description
        if (!$event->isAffiliate() && $this->isSPAMContent($event->getDescriptif())) {
            $event->getReject()->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        //Pas de dates valides fournies
        if (!$event->getDateDebut() instanceof \DateTimeInterface ||
            ($event->getDateFin() && !$event->getDateFin() instanceof \DateTimeInterface)
        ) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE);
        } elseif ($event->getDateFin() && $event->getDateFin() < $event->getDateDebut()) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
        }

        //Observation de l'événement
        if ($event->getExternalId()) {
            $exploration = $this->getExploration($event->getExternalId());
            if (!$exploration) {
                $exploration = (new Exploration())
                    ->setExternalId($event->getExternalId())
                    ->setLastUpdated($event->getExternalUpdatedAt())
                    ->setReject($event->getReject())
                    ->setReason($event->getReject()->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($event->getParserVersion());

                $this->addExploration($exploration);
            } else {
                //Pas besoin de paniquer l'EM si les dates sont équivalentes
                if ($exploration->getLastUpdated() !== $event->getExternalUpdatedAt()) {
                    $exploration->setLastUpdated($event->getExternalUpdatedAt());
                }

                $exploration
                    ->setReject($event->getReject())
                    ->setReason($event->getReject()->getReason());
            }
        }
    }

    public function checkMinLengthValidity($str, $min)
    {
        return isset(\trim($str)[$min]);
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
            if (\mb_strstr($content, $black_word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $externalId
     *
     * @return Exploration|null
     */
    public function getExploration($externalId)
    {
        if (!isset($this->explorations[$externalId])) {
            return null;
        }

        return $this->explorations[$externalId];
    }

    private function filterEventPlace(Event $event)
    {
        //Le nom du lieu doit comporter au moins 2 caractères
        if (!$this->checkMinLengthValidity($event->getPlaceName(), 2)) {
            $event->getPlaceReject()->addReason(Reject::BAD_PLACE_NAME);
        }

        $codePostal = $this->comparator->sanitizeNumber($event->getPlacePostalCode());
        if (!$this->checkLengthValidity($codePostal, 0) && !$this->checkLengthValidity($codePostal, 5)) {
            $event->getPlaceReject()->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
        }

        //Observation du lieu
        if ($event->getPlaceExternalId()) {
            $exploration = $this->getExploration($event->getPlaceExternalId());
            if (!$exploration) {
                $exploration = (new Exploration())
                    ->setExternalId($event->getPlaceExternalId())
                    ->setReject($event->getPlaceReject())
                    ->setReason($event->getPlaceReject()->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($event->getParserVersion());
                $this->addExploration($exploration);
            } else {
                $exploration
                    ->setReject($event->getPlaceReject())
                    ->setReason($event->getPlaceReject()->getReason());
            }
        }
    }

    private function checkLengthValidity($str, $length)
    {
        return \mb_strlen($this->comparator->sanitize($str)) === $length;
    }

    public function filterEventLocation(Event $event)
    {
        if (!$event->getPlace() || !$event->getPlace()->getReject()) {
            return;
        }

        $reject = $event->getPlace()->getReject();
        if (!$reject->isValid()) {
            $event->getPlaceReject()->addReason($reject->getReason());
            $event->getReject()->addReason($reject->getReason());
        }
    }

    public function filterEventExploration(Exploration $exploration, Event $event)
    {
        $reject = $exploration->getReject();

        //Aucune action sur un événement supprimé sur la plateforme par son créateur
        if ($reject->isEventDeleted()) {
            return;
        }

        $hasFirewallVersionChanged = $this->hasExplorationToBeUpdated($exploration, $event);
        $hasToBeUpdated = $this->hasEventToBeUpdated($exploration, $event);

        //L'évémenement n'a pas changé -> non valide
        if (!$hasToBeUpdated && !$reject->hasNoNeedToUpdate()) {
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
        //L'événement a changé -> valide
        } elseif ($hasToBeUpdated && $reject->hasNoNeedToUpdate()) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        //L'exploration est ancienne -> maj de la version
        if ($hasFirewallVersionChanged) {
            $exploration
                ->setFirewallVersion(self::VERSION)
                ->setParserVersion($event->getParserVersion());

            //L'événement n'était pas valide -> valide
            if (!$reject->hasNoNeedToUpdate()) {
                $reject->setValid();
            }
        }
    }

    public function hasEventToBeUpdated(Exploration $exploration, Event $event)
    {
        $explorationDate = $exploration->getLastUpdated();
        $eventDateModification = $event->getExternalUpdatedAt();

        if (!$explorationDate || !$eventDateModification) {
            return true;
        }

        if ($eventDateModification > $explorationDate) {
            return true;
        }

        return false;
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
