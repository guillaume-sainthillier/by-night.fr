<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\Event;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use DateTimeInterface;

class Firewall
{
    const VERSION = '1.1';

    /** @var ParserData[] */
    private array $parserDatas;

    private Comparator $comparator;

    private ParserDataRepository $parserDataRepository;

    public function __construct(Comparator $comparator, ParserDataRepository $parserDataRepository)
    {
        $this->comparator = $comparator;
        $this->parserDatas = [];
        $this->parserDataRepository = $parserDataRepository;
    }

    public function loadParserDatas(array $ids)
    {
        $parserDatas = $this->parserDataRepository->findBy([
            'externalId' => $ids,
        ]);

        foreach ($parserDatas as $parserData) {
            $this->addParserData($parserData);
        }
    }

    public function addParserData(ParserData $parserData)
    {
        $reject = new Reject();
        $reject->setReason($parserData->getReason());

        $this->parserDatas[$parserData->getExternalId()] = $parserData->setReject($reject);
    }

    public function hasPlaceToBeUpdated(ParserData $parserData, Event $event)
    {
        return $this->hasExplorationToBeUpdated($parserData, $event);
    }

    private function hasExplorationToBeUpdated(ParserData $parserData, Event $event)
    {
        return self::VERSION !== $parserData->getFirewallVersion() || $event->getParserVersion() !== $parserData->getFirewallVersion();
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
        if (!$event->getDateDebut() instanceof DateTimeInterface ||
            ($event->getDateFin() && !$event->getDateFin() instanceof DateTimeInterface)
        ) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE);
        } elseif ($event->getDateFin() && $event->getDateFin() < $event->getDateDebut()) {
            $event->getReject()->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
        }

        //Observation de l'événement
        if ($event->getExternalId()) {
            $parserData = $this->getExploration($event->getExternalId());
            if (null === $parserData) {
                $parserData = (new ParserData())
                    ->setExternalId($event->getExternalId())
                    ->setLastUpdated($event->getExternalUpdatedAt())
                    ->setReject($event->getReject())
                    ->setReason($event->getReject()->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($event->getParserVersion());

                $this->addParserData($parserData);
            } else {
                //Pas besoin de paniquer l'EM si les dates sont équivalentes
                if ($parserData->getLastUpdated() !== $event->getExternalUpdatedAt()) {
                    $parserData->setLastUpdated($event->getExternalUpdatedAt());
                }

                $parserData
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
            'Online-Streaming', 'DvdRip', 'Online HD Movies', 'FULL Movie Online Free', 'DvdRip-USA',
            '4K.Downloads', 'Super.4K.Videos', 'Free Trial Access',
            'Streaming Vf', 'vostfr', 'Film Streaming', 'Film Françaais',
            'Films VF', 'Film gratuit en streaming', 'HD-TV', 'DVD-Rip', 'VOSTFRdotCC!',
            'join the Illuminati', 'anti-breeze financial institution', 'call on +', 'Call Or Whats App On',
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
     * @return ParserData|null
     */
    public function getExploration($externalId)
    {
        if (!isset($this->parserDatas[$externalId])) {
            return null;
        }

        return $this->parserDatas[$externalId];
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
            $parserData = $this->getExploration($event->getPlaceExternalId());
            if (null === $parserData) {
                $parserData = (new ParserData())
                    ->setExternalId($event->getPlaceExternalId())
                    ->setReject($event->getPlaceReject())
                    ->setReason($event->getPlaceReject()->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($event->getParserVersion());
                $this->addParserData($parserData);
            } else {
                $parserData
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

    public function filterEventExploration(ParserData $parserData, Event $event)
    {
        $reject = $parserData->getReject();

        //Aucune action sur un événement supprimé sur la plateforme par son créateur
        if ($reject->isEventDeleted()) {
            return;
        }

        $hasFirewallVersionChanged = $this->hasExplorationToBeUpdated($parserData, $event);
        $hasToBeUpdated = $this->hasEventToBeUpdated($parserData, $event);

        //L'évémenement n'a pas changé -> non valide
        if (!$hasToBeUpdated && !$reject->hasNoNeedToUpdate()) {
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
        //L'événement a changé -> valide
        } elseif ($hasToBeUpdated && $reject->hasNoNeedToUpdate()) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        //L'exploration est ancienne -> maj de la version
        if ($hasFirewallVersionChanged) {
            $parserData
                ->setFirewallVersion(self::VERSION)
                ->setParserVersion($event->getParserVersion());

            if (!$reject->hasNoNeedToUpdate()) {
                $reject->setValid();
            }
        }
    }

    public function hasEventToBeUpdated(ParserData $parserData, Event $event)
    {
        $parserDataDate = $parserData->getLastUpdated();
        $eventDateModification = $event->getExternalUpdatedAt();

        if (!$parserDataDate || !$eventDateModification) {
            return true;
        }

        return $eventDateModification > $parserDataDate;
    }

    /**
     * @return ParserData[]
     */
    public function getParserDatas(): array
    {
        return $this->parserDatas;
    }

    public function flushParserDatas(): void
    {
        unset($this->parserDatas);
        $this->parserDatas = [];
    }
}
