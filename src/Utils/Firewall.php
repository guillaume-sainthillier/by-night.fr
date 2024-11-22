<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Dto\EventDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use DateTime;
use DateTimeInterface;

final class Firewall
{
    /**
     * @var string
     */
    public const VERSION = '1.1';

    /** @var ParserData[] */
    private array $parserDatas = [];

    public function __construct(private readonly Comparator $comparator, private readonly ParserDataRepository $parserDataRepository)
    {
    }

    public function loadExternalIdsData(array $ids): void
    {
        $parserDatas = $this->parserDataRepository->findBy([
            'externalId' => $ids,
        ]);

        foreach ($parserDatas as $parserData) {
            $this->addParserData($parserData);
        }
    }

    public function addParserData(ParserData $parserData): void
    {
        $reject = new Reject();
        $reject->setReason($parserData->getReason());

        $this->parserDatas[$parserData->getExternalId()] = $parserData->setReject($reject);
    }

    public function hasPlaceToBeUpdated(ParserData $parserData, EventDto $dto): bool
    {
        return $this->hasExplorationToBeUpdated($parserData, $dto);
    }

    private function hasExplorationToBeUpdated(ParserData $parserData, EventDto $dto): bool
    {
        return self::VERSION !== $parserData->getFirewallVersion() || $dto->parserVersion !== $parserData->getFirewallVersion();
    }

    public function isEventDtoValid(EventDto $eventDto): bool
    {
        return null === $eventDto->reject
        || null === $eventDto->place?->reject
        || ($eventDto->reject->isValid() && $eventDto->place->reject->isValid());
    }

    public function filterEvent(EventDto $dto): void
    {
        $this->filterEventInfos($dto);
        $this->filterEventPlace($dto);
        $this->mapPlaceRejectToEvent($dto);
    }

    private function filterEventInfos(EventDto $dto): void
    {
        // Le nom de l'événement doit comporter au moins 3 caractères
        if (!$dto->isAffiliate() && !$this->checkMinLengthValidity($dto->name, 3)) {
            $dto->reject->addReason(Reject::BAD_EVENT_NAME);
        }

        // La description de l'événement doit comporter au moins 20 caractères
        if (!$dto->isAffiliate() && !$this->checkMinLengthValidity($dto->description, 10)) {
            $dto->reject->addReason(Reject::BAD_EVENT_DESCRIPTION);
        }

        // Pas de SPAM dans la description
        if (!$dto->isAffiliate() && $this->isSPAMContent($dto->description)) {
            $dto->reject->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        // Pas de SPAM dans le titre
        if (!$dto->isAffiliate() && $this->isSPAMContent($dto->name)) {
            $dto->reject->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        // Pas de dates valides fournies
        if (!$dto->startDate instanceof DateTimeInterface
            || !$dto->endDate instanceof DateTimeInterface
        ) {
            $dto->reject->addReason(Reject::BAD_EVENT_DATE);
        } elseif ($dto->endDate < $dto->startDate) {
            $dto->reject->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
        }

        // Observation de l'événement
        if ($dto->getExternalId()) {
            $parserData = $this->getExploration($dto->getExternalId());
            if (null === $parserData) {
                $parserData = (new ParserData())
                    ->setExternalId($dto->getExternalId())
                    ->setExternalOrigin($dto->getExternalOrigin())
                    ->setLastUpdated(DateTime::createFromInterface($dto->getExternalUpdatedAt()))
                    ->setReject($dto->reject)
                    ->setReason($dto->reject->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($dto->parserVersion);

                $this->addParserData($parserData);
            } else {
                // Pas besoin de paniquer l'EM si les dates sont équivalentes
                if ($parserData->getLastUpdated()?->format('Y-m-d H:i:s') !== $dto->getExternalUpdatedAt()?->format('Y-m-d H:i:s')) {
                    $parserData->setLastUpdated(DateTime::createFromInterface($dto->getExternalUpdatedAt()));
                }

                $parserData
                    ->setReject($dto->reject)
                    ->setReason($dto->reject->getReason());
            }
        }
    }

    public function checkMinLengthValidity(?string $str, int $min): bool
    {
        return isset(trim($str ?? '')[$min]);
    }

    private function isSPAMContent(?string $content): bool
    {
        $content = mb_strtolower($content ?? '');
        $black_list = array_map('mb_strtolower', [
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
            'storiesdown.com', 'view Instagram stories',
            'TEMOIGNAGE DE PRET', 'prêteur', 'prêteuse', 'preteur', 'preteuse', 'prêt entre particulier',
            'offre de prët', 'offre de prêt', 'offres de prêt', 'RETOUR AFFECTIF', "retour d'amour", 'Retour d’affection',
            'grand marabout', 'ENVOÛTEMENT AMOUREUX', 'faire revenir un homme', "rituel d'amour", 'Retour de l’être aimé',
            "Retour de l'être aimé", 'valise magique',
        ]);

        foreach ($black_list as $black_word) {
            if (mb_strstr($content, $black_word)) {
                return true;
            }
        }

        return false;
    }

    public function getExploration(?string $externalId): ?ParserData
    {
        if (!isset($this->parserDatas[$externalId])) {
            return null;
        }

        return $this->parserDatas[$externalId];
    }

    private function filterEventPlace(EventDto $dto): void
    {
        if (null === $dto->place) {
            $dto->reject->addReason(Reject::NO_PLACE_PROVIDED);

            return;
        }

        // Le nom du lieu doit comporter au moins 2 caractères
        if (!$this->checkMinLengthValidity($dto->place->name, 2)) {
            $dto->place->reject->addReason(Reject::BAD_PLACE_NAME);
        }

        $codePostal = $this->comparator->sanitizeNumber($dto->place->city?->postalCode);
        if (!$this->checkLengthValidity($codePostal, 0) && !$this->checkLengthValidity($codePostal, 5)) {
            $dto->place->reject->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
        }

        // Observation du lieu
        if (null !== $dto->place->getExternalId()) {
            $parserData = $this->getExploration($dto->place->getExternalId());
            if (null === $parserData) {
                $parserData = (new ParserData())
                    ->setExternalId($dto->place->getExternalId())
                    ->setExternalOrigin($dto->place->getExternalOrigin())
                    ->setReject($dto->place->reject)
                    ->setReason($dto->place->reject->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($dto->parserVersion);
                $this->addParserData($parserData);
            } else {
                $parserData
                    ->setReject($dto->place->reject)
                    ->setReason($dto->place->reject->getReason());
            }
        }
    }

    private function checkLengthValidity(?string $str, int $length): bool
    {
        return mb_strlen($this->comparator->sanitize($str)) === $length;
    }

    private function mapPlaceRejectToEvent(EventDto $dto): void
    {
        if (null === $dto->place || null === $dto->place->reject) {
            return;
        }

        $reject = $dto->place->reject;
        if (!$reject->isValid()) {
            $dto->reject->addReason($reject->getReason());
        }
    }

    public function filterEventExploration(ParserData $parserData, EventDto $eventDto): void
    {
        $reject = $parserData->getReject();

        // Aucune action sur un événement supprimé sur la plateforme par son créateur
        if ($reject->isEventDeleted()) {
            return;
        }

        $hasFirewallVersionChanged = $this->hasExplorationToBeUpdated($parserData, $eventDto);
        $hasToBeUpdated = $this->hasEventToBeUpdated($parserData, $eventDto);

        // L'évémenement n'a pas changé -> non valide
        if (!$hasToBeUpdated && !$reject->hasNoNeedToUpdate()) {
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
        // L'événement a changé -> valide
        } elseif ($hasToBeUpdated && $reject->hasNoNeedToUpdate()) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        // L'exploration est ancienne -> maj de la version
        if ($hasFirewallVersionChanged) {
            $parserData
                ->setFirewallVersion(self::VERSION)
                ->setParserVersion($eventDto->parserVersion);

            if (!$reject->hasNoNeedToUpdate()) {
                $reject->setValid();
            }
        }
    }

    public function hasEventToBeUpdated(ParserData $parserData, EventDto $dto): bool
    {
        $parserDataDate = $parserData->getLastUpdated();
        $eventDateModification = $dto->getExternalUpdatedAt();

        if (!$parserDataDate || !$eventDateModification) {
            return true;
        }

        return $eventDateModification > $parserDataDate;
    }

    /**
     * @return ParserData[]
     */
    public function getExplorations(): array
    {
        return $this->parserDatas;
    }

    public function flushParserDatas(): void
    {
        unset($this->parserDatas);
        $this->parserDatas = [];
    }
}
