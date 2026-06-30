<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Contracts\BatchResetInterface;
use App\Dto\EventDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class Firewall implements BatchResetInterface
{
    public const string VERSION = '1.1';

    /** @var ParserData[] */
    private array $parserDatas = [];

    public function __construct(private readonly Comparator $comparator, private readonly ParserDataRepository $parserDataRepository, private readonly EventContentHasher $contentHasher, private readonly EventChangeDetector $changeDetector)
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
        // Place explorations carry no content fingerprint, so a version delta is all we
        // have to decide whether to re-observe them.
        return $this->changeDetector->hasVersionChanged($dto, $parserData->getFirewallVersion(), $parserData->getParserVersion());
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
        // Event name must have at least 3 characters
        if (!$dto->isAffiliate() && !$this->checkMinLengthValidity($dto->name, 3)) {
            $dto->reject->addReason(Reject::BAD_EVENT_NAME);
        }

        // Event description must have at least 10 characters
        if (!$dto->isAffiliate() && !$this->checkMinLengthValidity($dto->description, 10)) {
            $dto->reject->addReason(Reject::BAD_EVENT_DESCRIPTION);
        }

        // No SPAM in description
        if (!$dto->isAffiliate() && $this->isSPAMContent($dto->description)) {
            $dto->reject->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        // No SPAM in title
        if (!$dto->isAffiliate() && $this->isSPAMContent($dto->name)) {
            $dto->reject->addReason(Reject::SPAM_EVENT_DESCRIPTION);
        }

        // Validate dates - either from timesheets or direct fields
        if ([] !== $dto->timesheets) {
            // Validate each timesheet entry
            foreach ($dto->timesheets as $timesheet) {
                if (!$timesheet->startAt instanceof DateTimeInterface
                    || !$timesheet->endAt instanceof DateTimeInterface
                ) {
                    $dto->reject->addReason(Reject::BAD_EVENT_DATE);
                    break;
                }

                if ($timesheet->endAt < $timesheet->startAt) {
                    $dto->reject->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
                    break;
                }
            }
        } elseif (!$dto->startDate instanceof DateTimeInterface
            || !$dto->endDate instanceof DateTimeInterface
        ) {
            // No valid dates provided
            $dto->reject->addReason(Reject::BAD_EVENT_DATE);
        } elseif ($dto->endDate < $dto->startDate) {
            $dto->reject->addReason(Reject::BAD_EVENT_DATE_INTERVAL);
        }

        // Event observation
        if ($dto->getExternalId()) {
            $parserData = $this->getExploration($dto->getExternalId());
            if (null === $parserData) {
                $parserData = new ParserData()
                    ->setExternalId($dto->getExternalId())
                    ->setExternalOrigin($dto->getExternalOrigin())
                    ->setLastUpdated(null === $dto->getExternalUpdatedAt() ? null : DateTimeImmutable::createFromInterface($dto->getExternalUpdatedAt()))
                    ->setReject($dto->reject)
                    ->setReason($dto->reject->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($dto->parserVersion)
                    ->setContentHash($this->contentHasher->hash($dto));

                $this->addParserData($parserData);
            } else {
                // No need to panic the EM if dates are equivalent
                if ($parserData->getLastUpdated()?->format('Y-m-d H:i:s') !== $dto->getExternalUpdatedAt()?->format('Y-m-d H:i:s')) {
                    $parserData->setLastUpdated(null === $dto->getExternalUpdatedAt() ? null : DateTimeImmutable::createFromInterface($dto->getExternalUpdatedAt()));
                }

                $parserData
                    ->setReject($dto->reject)
                    ->setReason($dto->reject->getReason())
                    ->setContentHash($this->contentHasher->hash($dto));
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
        $black_list = array_map(mb_strtolower(...), [
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

        return array_any($black_list, static fn ($black_word) => mb_strstr($content, (string) $black_word));
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
                $parserData = new ParserData()
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

        // Decide "has it changed?" against the PREVIOUSLY stored signature, using the
        // exact same content-hash rule the publish-time guard applied. This must happen
        // before we overwrite the fingerprint below.
        $hasChanged = $this->changeDetector->hasChanged(
            $eventDto,
            $parserData->getContentHash(),
            $parserData->getFirewallVersion(),
            $parserData->getParserVersion(),
        );
        $hasVersionChanged = $this->changeDetector->hasVersionChanged(
            $eventDto,
            $parserData->getFirewallVersion(),
            $parserData->getParserVersion(),
        );

        // Always refresh the stored fingerprint, even on the early-return paths below:
        // otherwise a permanently-rejected (or deleted) event whose feed keeps mutating
        // would fail the publish-time hash check and be re-enqueued on every run.
        $parserData->setContentHash($this->contentHasher->hash($eventDto));

        // Aucune action sur un événement supprimé sur la plateforme par son créateur
        if ($reject->isEventDeleted()) {
            return;
        }

        // L'évémenement n'a pas changé -> non valide
        if (!$hasChanged && !$reject->hasNoNeedToUpdate()) {
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
        // L'événement a changé -> valide
        } elseif ($hasChanged && $reject->hasNoNeedToUpdate()) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        // L'exploration est ancienne -> maj de la version
        if ($hasVersionChanged) {
            $parserData
                ->setFirewallVersion(self::VERSION)
                ->setParserVersion($eventDto->parserVersion);

            if (!$reject->hasNoNeedToUpdate()) {
                $reject->setValid();
            }
        }
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

    public function batchReset(): void
    {
        $this->flushParserDatas();
    }
}
