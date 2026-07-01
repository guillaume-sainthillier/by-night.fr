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

/**
 * Single source of truth for the question "has this event changed since we last saw it?".
 *
 * Historically this was answered in two different places with two different signals:
 *   - {@see EventPublicationGuard} (before the queue) compared a content fingerprint;
 *   - {@see Firewall} (after the queue) compared the feed's own `updatedAt` timestamp.
 *
 * Those could disagree: a feed that edits an event's content WITHOUT bumping its
 * timestamp was published by the guard but then dropped by the firewall, so the edit
 * was silently lost. Both gates now delegate here, so the rule is defined exactly once
 * and stays consistent on both sides of the queue.
 */
final readonly class EventChangeDetector
{
    public function __construct(private EventContentHasher $contentHasher)
    {
    }

    /**
     * Whether the event must be (re)processed compared to the signature stored on the
     * previous run's {@see \App\Entity\ParserData}.
     */
    public function hasChanged(
        EventDto $dto,
        ?string $knownContentHash,
        ?string $knownFirewallVersion,
        ?string $knownParserVersion,
    ): bool {
        // Firewall or parser logic changed since we stored this event → re-evaluate it
        // regardless of content, because the previous verdict may no longer hold.
        if ($this->hasVersionChanged($dto, $knownFirewallVersion, $knownParserVersion)) {
            return true;
        }

        // Legacy row stored before fingerprints existed → process once to backfill it.
        if (null === $knownContentHash) {
            return true;
        }

        // Same versions, known fingerprint → changed only when the content differs.
        return $this->contentHasher->hash($dto) !== $knownContentHash;
    }

    /**
     * Whether the firewall or parser logic version differs from what we stored.
     *
     * Kept separate because place explorations carry no content fingerprint, and the
     * firewall still needs the version delta on its own to know when to re-validate a
     * previously rejected event.
     */
    public function hasVersionChanged(
        EventDto $dto,
        ?string $knownFirewallVersion,
        ?string $knownParserVersion,
    ): bool {
        return Firewall::VERSION !== $knownFirewallVersion || $dto->parserVersion !== $knownParserVersion;
    }
}
