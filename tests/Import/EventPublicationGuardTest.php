<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import;

use App\Dto\EventDto;
use App\Entity\ParserData;
use App\Factory\ParserDataFactory;
use App\Import\EventChangeDetector;
use App\Import\EventContentHasher;
use App\Import\EventPublicationGuard;
use App\Import\Firewall;
use App\Repository\ParserDataRepository;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\delete;

final class EventPublicationGuardTest extends AppKernelTestCase
{
    private EventContentHasher $hasher;

    private EventPublicationGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hasher = new EventContentHasher();
        // Real repository against the test DB so the lookup SQL is exercised end to end,
        // not stubbed. The guard delegates the "has it changed?" verdict to the shared
        // EventChangeDetector — the same rule the consumer-side Firewall applies.
        $this->guard = new EventPublicationGuard(
            self::getContainer()->get(ParserDataRepository::class),
            new EventChangeDetector($this->hasher),
        );
    }

    public function testPublishesWhenNoExternalIdentity(): void
    {
        $dto = new EventDto();
        $dto->name = 'Anonymous event';

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testPublishesBrandNewEvent(): void
    {
        self::assertTrue($this->guard->shouldPublish($this->event()));
    }

    public function testSkipsUnchangedEvent(): void
    {
        $dto = $this->event();
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => $dto->parserVersion,
            'contentHash' => $this->hasher->hash($dto),
        ]);

        self::assertFalse($this->guard->shouldPublish($dto), 'Identical content + versions must not be re-enqueued.');
    }

    public function testPublishesChangedEvent(): void
    {
        $dto = $this->event();
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => $dto->parserVersion,
            'contentHash' => $this->hasher->hash($dto),
        ]);

        $dto->name = 'A brand new title';

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testPublishesWhenFirewallVersionChanged(): void
    {
        $dto = $this->event();
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => '0.0',
            'parserVersion' => $dto->parserVersion,
            'contentHash' => $this->hasher->hash($dto),
        ]);

        self::assertTrue($this->guard->shouldPublish($dto), 'A firewall logic change must force re-evaluation.');
    }

    public function testPublishesWhenParserVersionChanged(): void
    {
        $dto = $this->event();
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => '0.9',
            'contentHash' => $this->hasher->hash($dto),
        ]);

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testPublishesWhenStoredHashIsNull(): void
    {
        $dto = $this->event();
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => $dto->parserVersion,
            'contentHash' => null,
        ]);

        self::assertTrue($this->guard->shouldPublish($dto), 'A legacy row without a fingerprint must be republished to backfill it.');
    }

    public function testSignatureFromAnotherOriginDoesNotMatch(): void
    {
        $dto = $this->event();
        // Same externalId, different origin: must be treated as a new event for this feed.
        ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => 'datatourisme',
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => $dto->parserVersion,
            'contentHash' => $this->hasher->hash($dto),
        ]);

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testAPrefetchedEventIsCheckedWithoutALookupOfItsOwn(): void
    {
        $dto = $this->event();
        $row = $this->storeSignatureOf($dto);

        $this->guard->prefetch('openagenda', ['evt-1']);
        // Gone from the table: only the prefetched signature can still say "unchanged"
        delete($row);

        self::assertFalse($this->guard->shouldPublish($dto));
    }

    public function testAnEventAbsentFromThePrefetchedChunkIsNew(): void
    {
        $dto = $this->event();

        $this->guard->prefetch('openagenda', ['evt-1']);
        // Stored after the prefetch: the chunk already knows the event as new
        $this->storeSignatureOf($dto);

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testAnEventOutsideThePrefetchedChunkIsLookedUp(): void
    {
        $dto = $this->event();
        $this->storeSignatureOf($dto);

        $this->guard->prefetch('openagenda', ['evt-2']);

        self::assertFalse($this->guard->shouldPublish($dto));
    }

    public function testAChunkPrefetchedForAnotherOriginDoesNotAnswer(): void
    {
        $dto = $this->event();
        $this->storeSignatureOf($dto);

        $this->guard->prefetch('datatourisme', ['evt-1']);

        self::assertFalse($this->guard->shouldPublish($dto), 'Looked up for its own origin, where it is known and unchanged.');
    }

    public function testResetForgetsThePrefetchedChunk(): void
    {
        $dto = $this->event();
        $row = $this->storeSignatureOf($dto);

        $this->guard->prefetch('openagenda', ['evt-1']);
        delete($row);
        $this->guard->reset();

        self::assertTrue($this->guard->shouldPublish($dto));
    }

    public function testNumericIdsArePrefetchedAlongsideAlphanumericOnes(): void
    {
        $numeric = $this->event('111293');
        $alphanumeric = $this->event('FMABRE035V52S8P2');
        $rows = [$this->storeSignatureOf($numeric), $this->storeSignatureOf($alphanumeric)];

        // The numeric id first: bound as an integer, the IN() would match nothing useful
        $this->guard->prefetch('openagenda', ['111293', 'FMABRE035V52S8P2', null, ' ']);
        foreach ($rows as $row) {
            delete($row);
        }

        self::assertFalse($this->guard->shouldPublish($numeric));
        self::assertFalse($this->guard->shouldPublish($alphanumeric));
    }

    private function storeSignatureOf(EventDto $dto): ParserData
    {
        return ParserDataFactory::createOne([
            'externalId' => $dto->externalId,
            'externalOrigin' => $dto->externalOrigin,
            'firewallVersion' => Firewall::VERSION,
            'parserVersion' => $dto->parserVersion,
            'contentHash' => $this->hasher->hash($dto),
        ]);
    }

    private function event(string $externalId = 'evt-1'): EventDto
    {
        $event = new EventDto();
        $event->externalId = $externalId;
        $event->externalOrigin = 'openagenda';
        $event->parserVersion = '1.0';
        $event->name = 'Concert';
        $event->description = 'A nice concert in town';

        return $event;
    }
}
