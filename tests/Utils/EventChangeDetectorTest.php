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
use App\Utils\EventChangeDetector;
use App\Utils\EventContentHasher;
use App\Utils\Firewall;
use PHPUnit\Framework\TestCase;

final class EventChangeDetectorTest extends TestCase
{
    private EventContentHasher $hasher;

    private EventChangeDetector $detector;

    protected function setUp(): void
    {
        $this->hasher = new EventContentHasher();
        $this->detector = new EventChangeDetector($this->hasher);
    }

    public function testUnchangedWhenSameVersionAndSameHash(): void
    {
        $dto = $this->event();

        self::assertFalse($this->detector->hasChanged(
            $dto,
            $this->hasher->hash($dto),
            Firewall::VERSION,
            $dto->parserVersion,
        ));
    }

    public function testChangedWhenContentDiffers(): void
    {
        $dto = $this->event();
        $storedHash = $this->hasher->hash($dto);
        $dto->name = 'A different title';

        self::assertTrue($this->detector->hasChanged(
            $dto,
            $storedHash,
            Firewall::VERSION,
            $dto->parserVersion,
        ));
    }

    public function testChangedWhenFirewallVersionDiffers(): void
    {
        $dto = $this->event();

        self::assertTrue($this->detector->hasChanged(
            $dto,
            $this->hasher->hash($dto),
            'old version',
            $dto->parserVersion,
        ));
    }

    public function testChangedWhenParserVersionDiffers(): void
    {
        $dto = $this->event();

        self::assertTrue($this->detector->hasChanged(
            $dto,
            $this->hasher->hash($dto),
            Firewall::VERSION,
            '0.9',
        ));
    }

    public function testChangedWhenStoredHashIsNull(): void
    {
        $dto = $this->event();

        self::assertTrue($this->detector->hasChanged(
            $dto,
            null,
            Firewall::VERSION,
            $dto->parserVersion,
        ), 'A legacy row without a fingerprint must be reprocessed to backfill it.');
    }

    public function testHasVersionChangedIgnoresContent(): void
    {
        $dto = $this->event();

        self::assertFalse($this->detector->hasVersionChanged($dto, Firewall::VERSION, $dto->parserVersion));
        self::assertTrue($this->detector->hasVersionChanged($dto, 'old version', $dto->parserVersion));
        self::assertTrue($this->detector->hasVersionChanged($dto, Firewall::VERSION, '0.9'));
    }

    private function event(): EventDto
    {
        $dto = new EventDto();
        $dto->parserVersion = '1.0';
        $dto->name = 'Concert';
        $dto->description = 'A nice concert in town';

        return $dto;
    }
}
