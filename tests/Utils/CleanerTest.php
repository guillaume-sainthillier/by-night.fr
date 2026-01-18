<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Dto\CityDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Tests\AppKernelTestCase;
use App\Utils\Cleaner;
use DateTime;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

class CleanerTest extends AppKernelTestCase
{
    private Cleaner $cleaner;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleaner = static::getContainer()->get(Cleaner::class);
    }

    public function testCleanEventSetsEndDateWhenNull(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->endDate = null;

        $this->cleaner->cleanEvent($dto);

        self::assertNotNull($dto->endDate);
        self::assertEquals($dto->startDate, $dto->endDate);
    }

    public function testCleanEventTrimsWhitespace(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->name = '  Test Event  ';
        $dto->description = '  Event Description  ';

        $this->cleaner->cleanEvent($dto);

        self::assertEquals('Test Event', $dto->name);
        self::assertEquals('Event Description', $dto->description);
    }

    public function testCleanEventSetsNullForEmptyStrings(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->name = '   ';
        $dto->description = '';

        $this->cleaner->cleanEvent($dto);

        self::assertNull($dto->name);
        self::assertNull($dto->description);
    }

    public function testCleanEventTruncatesLongFields(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->address = str_repeat('a', 300);
        $dto->category = str_repeat('b', 150);
        $dto->theme = str_repeat('c', 150);

        $this->cleaner->cleanEvent($dto);

        self::assertNotNull($dto->address);
        self::assertNotNull($dto->category);
        self::assertNotNull($dto->theme);
        self::assertEquals(255, \strlen($dto->address));
        self::assertEquals(128, \strlen($dto->category));
        self::assertEquals(128, \strlen($dto->theme));
    }

    #[DataProvider('coordinatesProvider')]
    public function testCleanEventCleansCoordinates(?float $input, ?float $expected): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->latitude = $input;
        $dto->longitude = $input;

        $this->cleaner->cleanEvent($dto);

        self::assertEquals($expected, $dto->latitude);
        self::assertEquals($expected, $dto->longitude);
    }

    public static function coordinatesProvider(): array
    {
        return [
            [43.604652, 43.604652],
            [-1.444209, -1.444209],
            [null, null],
        ];
    }

    public function testCleanPlaceTrimsAndCleans(): void
    {
        $dto = new PlaceDto();
        $dto->name = '  Le Bikini  ';
        $dto->street = '  Rue Théodore Monod  ';

        $this->cleaner->cleanPlace($dto);

        self::assertEquals('Le Bikini', $dto->name);
        self::assertEquals('Rue Théodore Monod', $dto->street);
    }

    public function testCleanPlaceCleansCoordinates(): void
    {
        $dto = new PlaceDto();
        $dto->latitude = 43.604652;
        $dto->longitude = 1.444209;

        $this->cleaner->cleanPlace($dto);

        self::assertEquals(43.604652, $dto->latitude);
        self::assertEquals(1.444209, $dto->longitude);
    }

    public function testCleanCityRemovesNonNumericFromPostalCode(): void
    {
        $dto = new CityDto();
        $dto->postalCode = 'FR31000ABC';
        $dto->name = 'toulouse';

        $this->cleaner->cleanCity($dto);

        self::assertEquals('31000', $dto->postalCode);
    }

    public function testCleanCityCleansName(): void
    {
        $dto = new CityDto();
        $dto->name = 'saint - lys';

        $this->cleaner->cleanCity($dto);

        self::assertEquals('Saint-Lys', $dto->name);
    }
}
