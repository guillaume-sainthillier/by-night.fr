<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Dto\EventDto;
use App\Dto\PlaceDto;
use DateTimeInterface;

/**
 * Computes a stable content fingerprint for an {@see EventDto}.
 *
 * The hash answers a single question: "has anything we care about changed since
 * the last time we saw this event?". The import command compares this fingerprint
 * against the one stored on {@see \App\Entity\ParserData} to decide whether the
 * event is worth enqueueing again (see {@see EventPublicationGuard}).
 *
 * Identity (externalId/externalOrigin), bookkeeping (entityId, reject, parser
 * version) and the source's own updatedAt timestamp are intentionally left out:
 * they are either the lookup key or metadata, not the user-visible payload. A feed
 * that bumps its updatedAt without changing the content must NOT trigger a re-import.
 */
final class EventContentHasher
{
    public function hash(EventDto $dto): string
    {
        return sha1((string) json_encode($this->canonicalize($dto), \JSON_UNESCAPED_UNICODE));
    }

    /**
     * Fingerprint of the event *identity*: what makes two records of one source the
     * same event even though each carries its own dates. Two OpenAgenda uids created
     * for two sessions of the same workshop share it; the same title at the same venue
     * with another description does not.
     *
     * Deliberately left out: dates, timesheets, hours, the source URL (one per record)
     * and the image (re-uploaded per record). Null when the event cannot be grouped:
     * no origin (user-created), no name, or no place external id (a generic title at
     * an unidentified venue is no evidence of sameness).
     */
    public function identity(EventDto $dto): ?string
    {
        return $this->identityOf(
            $dto->externalOrigin,
            $dto->place?->externalId,
            $dto->name,
            $dto->description,
        );
    }

    /**
     * The one definition of "the same event", also computed from the stored columns of
     * rows imported before the hash existed (app:events:resolve-families).
     */
    public function identityOf(?string $origin, ?string $placeExternalId, ?string $name, ?string $description): ?string
    {
        if (null === $origin || null === $placeExternalId || null === $name || '' === $name) {
            return null;
        }

        return sha1((string) json_encode([
            'origin' => $origin,
            'placeExternalId' => $placeExternalId,
            'name' => $name,
            'description' => $description,
        ], \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalize(EventDto $dto): array
    {
        return [
            'name' => $dto->name,
            'description' => $dto->description,
            'startDate' => $this->date($dto->startDate),
            'endDate' => $this->date($dto->endDate),
            'hours' => $dto->hours,
            'prices' => $dto->prices,
            'type' => $dto->type,
            'source' => $dto->source,
            'status' => $dto->status?->value,
            'statusMessage' => $dto->statusMessage,
            'imageUrl' => $dto->imageUrl,
            'address' => $dto->address,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'websiteContacts' => $this->sortedList($dto->websiteContacts),
            'phoneContacts' => $this->sortedList($dto->phoneContacts),
            'emailContacts' => $this->sortedList($dto->emailContacts),
            'category' => $dto->category?->name,
            'themes' => $this->sortedList(array_map(static fn ($theme): ?string => $theme->name, $dto->themes)),
            'timesheets' => $this->timesheets($dto),
            'place' => $this->place($dto->place),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function place(?PlaceDto $place): ?array
    {
        if (null === $place) {
            return null;
        }

        return [
            'name' => $place->name,
            'street' => $place->street,
            'externalId' => $place->externalId,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'cityName' => $place->city?->name,
            'cityPostalCode' => $place->city?->postalCode,
            'countryCode' => $place->country?->code,
        ];
    }

    /**
     * Timesheets are sorted so a reordered-but-identical schedule keeps the same hash.
     *
     * @return list<array<string, mixed>>
     */
    private function timesheets(EventDto $dto): array
    {
        $timesheets = array_map(fn ($timesheet): array => [
            'startAt' => $this->date($timesheet->startAt),
            'endAt' => $this->date($timesheet->endAt),
            'hours' => $timesheet->hours,
        ], $dto->timesheets);

        sort($timesheets);

        return $timesheets;
    }

    private function date(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    /**
     * @param array<int, string|null>|null $values
     *
     * @return list<string>
     */
    private function sortedList(?array $values): array
    {
        $values = array_values(array_filter($values ?? [], static fn ($value): bool => null !== $value && '' !== $value));
        sort($values);

        return $values;
    }
}
