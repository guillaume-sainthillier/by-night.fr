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
use App\Repository\ParserDataRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Publish-time deduplication gate.
 *
 * Historically every parsed event was dispatched to RabbitMQ and only filtered out
 * on the consumer side ({@see Firewall}). A nightly run therefore enqueued ~57k
 * messages even when nothing changed. This guard moves the "has it changed?" check
 * in front of the queue: it compares the event's content fingerprint (and the
 * firewall/parser versions) against the previous run's {@see \App\Entity\ParserData}
 * and lets only NEW or CHANGED events through.
 *
 * Parsers publishing through {@see \App\Parser\AbstractParser::publishMany()} have the
 * signatures of each chunk {@see prefetch()}ed in one query; any other event costs a
 * lookup of its own.
 */
final class EventPublicationGuard implements ResetInterface
{
    /**
     * Signatures of the chunk being published, by origin then external id; null marks an
     * event looked up and absent (a new one).
     *
     * @var array<string, array<string, array{contentHash: ?string, firewallVersion: ?string, parserVersion: ?string}|null>>
     */
    private array $prefetched = [];

    public function __construct(
        private readonly ParserDataRepository $parserDataRepository,
        private readonly EventChangeDetector $changeDetector,
    ) {
    }

    /**
     * Loads the signatures of the events about to be published in one query, in place of
     * the previous chunk's: memory stays bounded whatever the size of the feed.
     *
     * @param iterable<string|null> $externalIds
     */
    public function prefetch(string $externalOrigin, iterable $externalIds): void
    {
        $this->prefetched = [];

        $known = [];
        foreach ($externalIds as $externalId) {
            if (null !== $externalId && '' !== trim($externalId)) {
                $known[$externalId] = null;
            }
        }

        $ids = array_map(strval(...), array_keys($known));
        foreach (array_chunk($ids, 1_000) as $chunk) {
            foreach ($this->parserDataRepository->findSignatures($externalOrigin, $chunk) as $externalId => $signature) {
                $known[$externalId] = $signature;
            }
        }

        $this->prefetched[$externalOrigin] = $known;
    }

    public function shouldPublish(EventDto $dto): bool
    {
        $externalId = $dto->getExternalId();
        $externalOrigin = $dto->getExternalOrigin();

        // Without a stable identity we cannot dedup — always publish.
        if (null === $externalId || null === $externalOrigin) {
            return true;
        }

        $signature = $this->getSignature($externalOrigin, $externalId);

        // Never seen before → new event.
        if (null === $signature) {
            return true;
        }

        // Publish only when the shared change rule says the event is new or changed —
        // the exact same rule the consumer-side Firewall applies, so the two gates can
        // never disagree across the queue.
        return $this->changeDetector->hasChanged(
            $dto,
            $signature['contentHash'],
            $signature['firewallVersion'],
            $signature['parserVersion'],
        );
    }

    public function reset(): void
    {
        $this->prefetched = [];
    }

    /**
     * @return array{contentHash: ?string, firewallVersion: ?string, parserVersion: ?string}|null
     */
    private function getSignature(string $externalOrigin, string $externalId): ?array
    {
        if (\array_key_exists($externalId, $this->prefetched[$externalOrigin] ?? [])) {
            return $this->prefetched[$externalOrigin][$externalId];
        }

        return $this->parserDataRepository->findSignatures($externalOrigin, [$externalId])[$externalId] ?? null;
    }
}
