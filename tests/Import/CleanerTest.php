<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import;

use App\Dto\CityDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Import\Cleaner;
use App\Import\EventContentHasher;
use App\Tests\AppKernelTestCase;
use DateTime;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class CleanerTest extends AppKernelTestCase
{
    private Cleaner $cleaner;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleaner = self::getContainer()->get(Cleaner::class);
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
        $dto->category = TagDto::fromString(str_repeat('b', 150));
        $dto->themes = [TagDto::fromString(str_repeat('c', 150))];

        $this->cleaner->cleanEvent($dto);

        self::assertNotNull($dto->address);
        self::assertNotNull($dto->category);
        self::assertCount(1, $dto->themes);
        self::assertEquals(255, \strlen($dto->address));
        self::assertEquals(128, \strlen((string) $dto->category->name));
        self::assertEquals(128, \strlen((string) $dto->themes[0]->name));
    }

    public function testCleanEventFitsTheEventColumns(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->name = str_repeat('é', 300);
        $dto->source = 'https://example.org/' . str_repeat('a', 300);
        $dto->imageUrl = 'https://example.org/' . str_repeat('b', 300) . '.jpg';

        $this->cleaner->cleanEvent($dto);

        self::assertSame(255, mb_strlen((string) $dto->name));
        self::assertNull($dto->source, 'A cut link would lead nowhere');
        self::assertNull($dto->imageUrl);
    }

    public function testCleanEventKeepsLinksThatFit(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->source = 'https://openagenda.com/agendas/1/events/2';
        $dto->imageUrl = 'https://cdn.openagenda.com/main/a.jpg';

        $this->cleaner->cleanEvent($dto);

        self::assertSame('https://openagenda.com/agendas/1/events/2', $dto->source);
        self::assertSame('https://cdn.openagenda.com/main/a.jpg', $dto->imageUrl);
    }

    public function testCleanPlaceAndCityFitTheirColumns(): void
    {
        $place = new PlaceDto();
        $place->name = str_repeat('Salle ', 60);
        $place->street = str_repeat('Chemin de Mi-Chemin ', 10);
        $city = new CityDto();
        $city->name = str_repeat('Saint-', 30);

        $this->cleaner->cleanPlace($place);
        $this->cleaner->cleanCity($city);

        self::assertLessThanOrEqual(255, mb_strlen((string) $place->name));
        self::assertLessThanOrEqual(127, mb_strlen((string) $place->street));
        self::assertLessThanOrEqual(127, mb_strlen((string) $city->name));
    }

    /**
     * @param string[]|null $websites
     * @param string[]|null $expected
     */
    #[DataProvider('websitesProvider')]
    public function testCleanEventKeepsOnlyTheWebsitesThatCanBeLinked(?array $websites, ?array $expected): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->websiteContacts = $websites;

        $this->cleaner->cleanEvent($dto);

        self::assertSame($expected, $dto->websiteContacts);
    }

    /**
     * @return iterable<string, array{string[]|null, string[]|null}>
     */
    public static function websitesProvider(): iterable
    {
        yield 'url is kept' => [['https://example.org/billets'], ['https://example.org/billets']];
        yield 'bare host is kept as typed' => [['www.toulouse-tourisme.com'], ['www.toulouse-tourisme.com']];
        yield 'surrounding spaces are trimmed' => [['  https://theatre-sorano.fr '], ['https://theatre-sorano.fr']];
        yield 'spaces in the query keep the url whole' => [['www.ardei-soft.com/spectacle.html?spectacle=La Dispute'], ['www.ardei-soft.com/spectacle.html?spectacle=La Dispute']];
        yield 'urls separated by a space are split' => [['www.abc-toulouse.fr www.fifigrot.com'], ['www.abc-toulouse.fr', 'www.fifigrot.com']];
        yield 'urls separated by a semicolon are split' => [['http://www.museemauricedenis.yvelines.fr;http://www.yvelines.fr'], ['http://www.museemauricedenis.yvelines.fr', 'http://www.yvelines.fr']];
        yield 'urls separated by a comma are split' => [['www.a.fr, www.b.fr'], ['www.a.fr', 'www.b.fr']];
        yield 'text around a url is dropped' => [['Billets : https://www.helloasso.com/associations/l-art-scenes'], ['https://www.helloasso.com/associations/l-art-scenes']];
        yield 'text after a url is dropped' => [['https://web.digitick.com/ez3kiel.html Etienne ANDRE'], ['https://web.digitick.com/ez3kiel.html']];
        yield 'duplicates are merged' => [['www.a.fr', 'www.a.fr www.b.fr'], ['www.a.fr', 'www.b.fr']];
        yield 'what cannot be linked is dropped' => [['92.05.40.65', 'javascript:alert(1)', 'sortir-a-toulouse/agenda', 'http://à venir', ''], null];
        yield 'empty list' => [[], null];
        yield 'null' => [null, null];
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

    private function cleanAll(EventDto $dto): void
    {
        // Mirror EventHandler::cleanEvent, the entry point used on both sides of the queue.
        $this->cleaner->cleanEvent($dto);
        if (null !== $dto->place) {
            $this->cleaner->cleanPlace($dto->place);
            if (null !== $dto->place->city) {
                $this->cleaner->cleanCity($dto->place->city);
            }
        }
    }

    private function messyEvent(): EventDto
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');
        $dto->name = '  Le   Grand    Concert  ';
        $dto->description = "  Une   soirée   d'exception  ";
        $dto->address = str_repeat('a', 300);
        $dto->hours = '  De 20h   à 23h  ';
        $dto->type = '  Concert  ';
        $dto->category = TagDto::fromString('  Musique  ');
        $dto->themes = [TagDto::fromString('  Rock  '), TagDto::fromString('  Jazz  ')];
        $dto->latitude = 43.604652;
        $dto->longitude = 1.444209;
        $dto->websiteContacts = ['  www.b.fr www.a.fr ', 'Http://C.fr', 'www.a.fr', 'à venir'];

        $timesheet = new EventTimesheetDto();
        $timesheet->hours = '  20h - 23h  ';
        $timesheet->startAt = new DateTime('2024-01-15 20:00:00');
        $timesheet->endAt = new DateTime('2024-01-15 23:00:00');

        $dto->timesheets = [$timesheet];

        $place = new PlaceDto();
        $place->name = '  le   bikini  ';
        $place->street = '  rue   théodore   monod  ';
        $place->latitude = 43.604652;
        $place->longitude = 1.444209;

        $city = new CityDto();
        $city->name = 'saint - lys';
        $city->postalCode = 'FR31000ABC';

        $place->city = $city;
        $dto->place = $place;

        return $dto;
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

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function providePostalCodes(): iterable
    {
        yield 'country prefix with a dash' => ['F-31000', '31000'];
        yield 'trailing dash' => ['95021-', '95021'];
        yield 'spaces' => [' 31 000 ', '31000'];
        yield 'nothing but punctuation' => ['.', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('providePostalCodes')]
    public function testCleanCityKeepsTheDigitsOfThePostalCode(?string $postalCode, ?string $expected): void
    {
        $dto = new CityDto();
        $dto->postalCode = $postalCode;

        $this->cleaner->cleanCity($dto);

        self::assertSame($expected, $dto->postalCode);
    }

    public function testCleanCityCleansName(): void
    {
        $dto = new CityDto();
        $dto->name = 'saint - lys';

        $this->cleaner->cleanCity($dto);

        self::assertEquals('Saint-Lys', $dto->name);
    }

    public function testCleanEventTimesheetTruncatesLongHours(): void
    {
        $dto = new EventTimesheetDto();
        $dto->hours = str_repeat('a', 300);

        $this->cleaner->cleanEventTimesheet($dto);

        self::assertNotNull($dto->hours);
        self::assertEquals(255, \strlen($dto->hours));
    }

    public function testCleanEventTimesheetSetsNullForEmptyHours(): void
    {
        $dto = new EventTimesheetDto();
        $dto->hours = '';

        $this->cleaner->cleanEventTimesheet($dto);

        self::assertNull($dto->hours);
    }

    public function testCleanEventTimesheetPreservesValidHours(): void
    {
        $dto = new EventTimesheetDto();
        $dto->hours = 'De 20h à 23h';

        $this->cleaner->cleanEventTimesheet($dto);

        self::assertEquals('De 20h à 23h', $dto->hours);
    }

    public function testCleaningIsIdempotentForTheDedupFingerprint(): void
    {
        // The publish-time guard hashes an event after one clean pass; the consumer
        // re-cleans the same event before storing its fingerprint. Both fingerprints
        // must match, which only holds if cleaning is idempotent. Lock that contract:
        // if a future clean rule stops being stable, the dedup gate would silently
        // re-enqueue every event on every run, and this test would catch it.
        $hasher = new EventContentHasher();

        $dto = $this->messyEvent();
        $this->cleanAll($dto);
        $firstPass = $hasher->hash($dto);

        $this->cleanAll($dto);
        $secondPass = $hasher->hash($dto);

        self::assertSame($firstPass, $secondPass);
    }

    public function testCleanEventCleansNestedTimesheets(): void
    {
        $dto = new EventDto();
        $dto->startDate = new DateTime('2024-01-15');

        $timesheet1 = new EventTimesheetDto();
        $timesheet1->hours = str_repeat('a', 300);
        $timesheet1->startAt = new DateTime('2024-01-15 10:00:00');
        $timesheet1->endAt = new DateTime('2024-01-15 18:00:00');

        $timesheet2 = new EventTimesheetDto();
        $timesheet2->hours = '';
        $timesheet2->startAt = new DateTime('2024-01-16 10:00:00');
        $timesheet2->endAt = new DateTime('2024-01-16 18:00:00');

        $dto->timesheets = [$timesheet1, $timesheet2];

        $this->cleaner->cleanEvent($dto);

        self::assertEquals(255, \strlen((string) $dto->timesheets[0]->hours));
        self::assertNull($dto->timesheets[1]->hours);
    }
}
