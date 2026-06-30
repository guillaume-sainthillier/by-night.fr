<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Dto\EventDto;
use App\Factory\ParserDataFactory;
use App\Repository\ParserDataRepository;
use App\Tests\AppKernelTestCase;
use App\Utils\EventChangeDetector;
use App\Utils\EventContentHasher;
use App\Utils\EventPublicationGuard;
use App\Utils\Firewall;
use Zenstruck\Foundry\Test\ResetDatabase;

final class EventPublicationGuardTest extends AppKernelTestCase
{
    use ResetDatabase;

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

    private function event(): EventDto
    {
        $event = new EventDto();
        $event->externalId = 'evt-1';
        $event->externalOrigin = 'openagenda';
        $event->parserVersion = '1.0';
        $event->name = 'Concert';
        $event->description = 'A nice concert in town';

        return $event;
    }
}
