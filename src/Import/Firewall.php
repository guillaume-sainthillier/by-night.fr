<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Contracts\BatchResetInterface;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Entity\ParserData;
use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use DateTimeImmutable;
use DateTimeInterface;

final class Firewall implements BatchResetInterface
{
    public const string VERSION = '1.2';

    /** @var array<string, ParserData> by origin and external id, see getKey() */
    private array $parserDatas = [];

    public function __construct(
        private readonly ParserDataRepository $parserDataRepository,
        private readonly EventContentHasher $contentHasher,
        private readonly EventChangeDetector $changeDetector,
        private readonly PostalCodeChecker $postalCodeChecker,
    ) {
    }

    /**
     * Explorations are stored by source and external id, and a source may number its events
     * and its venues alike (OpenAgenda does: thousands of event uids equal a location uid).
     * A venue's exploration is therefore kept under an origin of its own, or the event and
     * the venue would share one row and each verdict would overwrite the other.
     */
    public static function getPlaceExplorationOrigin(string $externalOrigin): string
    {
        return $externalOrigin . ':place';
    }

    /**
     * Loads the explorations of these events and of their venues.
     *
     * @param EventDto[] $dtos
     */
    public function loadExplorations(array $dtos): void
    {
        $idsByOrigin = [];
        foreach ($dtos as $dto) {
            if (null !== $dto->getExternalId() && null !== $dto->getExternalOrigin()) {
                $idsByOrigin[$dto->getExternalOrigin()][$dto->getExternalId()] = true;
            }

            $place = $dto->place;
            if (null !== $place?->getExternalId() && null !== $place->getExternalOrigin()) {
                $idsByOrigin[self::getPlaceExplorationOrigin($place->getExternalOrigin())][$place->getExternalId()] = true;
            }
        }

        foreach ($idsByOrigin as $origin => $ids) {
            // Numeric ids became integer keys
            foreach ($this->parserDataRepository->findByExternalIds((string) $origin, array_map(strval(...), array_keys($ids))) as $parserData) {
                $this->addParserData($parserData);
            }
        }
    }

    private function addParserData(ParserData $parserData): void
    {
        $reject = new Reject();
        $reject->setReason($parserData->getReason());

        $this->parserDatas[self::getKey((string) $parserData->getExternalOrigin(), (string) $parserData->getExternalId())] = $parserData->setReject($reject);
    }

    private static function getKey(string $externalOrigin, string $externalId): string
    {
        return $externalOrigin . "\n" . $externalId;
    }

    public function isEventDtoValid(EventDto $eventDto): bool
    {
        // Place-level rejects are folded into the event reject by mapPlaceRejectToEvent,
        // but we still check both explicitly. The previous `||` chain returned true as
        // soon as the place reject was null, so a place-less event (place === null) that
        // had been rejected with NO_PLACE_PROVIDED was wrongly kept.
        $isEventValid = $eventDto->reject?->isValid() ?? true;
        $isPlaceValid = $eventDto->place?->reject?->isValid() ?? true;

        return $isEventValid && $isPlaceValid;
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
        if (null !== $dto->getExternalId() && null !== $dto->getExternalOrigin()) {
            $parserData = $this->getEventExploration($dto);
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

    /**
     * At least $min characters once trimmed (isset($str[$min]) wanted one byte more: "Bal" failed a
     * minimum of 3, while two accented letters passed it).
     */
    public function checkMinLengthValidity(?string $str, int $min): bool
    {
        return mb_strlen(trim($str ?? '')) >= $min;
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

    public function getEventExploration(EventDto $dto): ?ParserData
    {
        if (null === $dto->getExternalId() || null === $dto->getExternalOrigin()) {
            return null;
        }

        return $this->parserDatas[self::getKey($dto->getExternalOrigin(), $dto->getExternalId())] ?? null;
    }

    public function getPlaceExploration(PlaceDto $dto): ?ParserData
    {
        if (null === $dto->getExternalId() || null === $dto->getExternalOrigin()) {
            return null;
        }

        return $this->parserDatas[self::getKey(self::getPlaceExplorationOrigin($dto->getExternalOrigin()), $dto->getExternalId())] ?? null;
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

        // The expected postal code format depends on the country (5 digits in France,
        // 4 in Switzerland or Belgium): the checker reads it from the Country row.
        if (!$this->postalCodeChecker->accepts($dto->place->country, $dto->place->city?->postalCode)) {
            $dto->place->reject->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
        }

        // Observation du lieu
        if (null !== $dto->place->getExternalId() && null !== $dto->place->getExternalOrigin()) {
            $parserData = $this->getPlaceExploration($dto->place);
            if (null === $parserData) {
                $parserData = new ParserData()
                    ->setExternalId($dto->place->getExternalId())
                    ->setExternalOrigin(self::getPlaceExplorationOrigin($dto->place->getExternalOrigin()))
                    ->setReject($dto->place->reject)
                    ->setReason($dto->place->reject->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($dto->parserVersion);
                $this->addParserData($parserData);
            } else {
                $parserData
                    ->setReject($dto->place->reject)
                    ->setReason($dto->place->reject->getReason())
                    ->setFirewallVersion(self::VERSION)
                    ->setParserVersion($dto->parserVersion);
            }
        }
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

        if (!$hasChanged) {
            // Nothing new since the previous verdict, which stands
            $reject->addReason(Reject::NO_NEED_TO_UPDATE);
        } else {
            // New content or new rules: the previous verdict no longer holds, filterEvent()
            // judges the event again. Only lifting NO_NEED_TO_UPDATE kept an event rejected
            // once (a description too short, a bad date) out for good, whatever the source
            // fixed since.
            $reject->setValid();
        }

        // L'exploration est ancienne -> maj de la version
        if ($hasVersionChanged) {
            $parserData
                ->setFirewallVersion(self::VERSION)
                ->setParserVersion($eventDto->parserVersion);
        }
    }

    /**
     * @return array<string, ParserData>
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
