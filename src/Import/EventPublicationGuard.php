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

/**
 * Publish-time deduplication gate.
 *
 * Historically every parsed event was dispatched to RabbitMQ and only filtered out
 * on the consumer side ({@see Firewall}). A nightly run therefore enqueued ~57k
 * messages even when nothing changed. This guard moves the "has it changed?" check
 * in front of the queue: it compares the event's content fingerprint (and the
 * firewall/parser versions) against the previous run's {@see \App\Entity\ParserData}
 * and lets only NEW or CHANGED events through.
 */
final readonly class EventPublicationGuard
{
    public function __construct(
        private ParserDataRepository $parserDataRepository,
        private EventChangeDetector $changeDetector,
    ) {
    }

    public function shouldPublish(EventDto $dto): bool
    {
        $externalId = $dto->getExternalId();
        $externalOrigin = $dto->getExternalOrigin();

        // Without a stable identity we cannot dedup — always publish.
        if (null === $externalId || null === $externalOrigin) {
            return true;
        }

        $parserData = $this->parserDataRepository->findOneBy([
            'externalId' => $externalId,
            'externalOrigin' => $externalOrigin,
        ]);

        // Never seen before → new event.
        if (null === $parserData) {
            return true;
        }

        // Publish only when the shared change rule says the event is new or changed —
        // the exact same rule the consumer-side Firewall applies, so the two gates can
        // never disagree across the queue.
        return $this->changeDetector->hasChanged(
            $dto,
            $parserData->getContentHash(),
            $parserData->getFirewallVersion(),
            $parserData->getParserVersion(),
        );
    }
}
