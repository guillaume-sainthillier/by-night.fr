<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import\CrossSource;

use App\Entity\Event;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\CrossSourceLinkFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Import\CrossSource\CrossSourceLinker;
use App\Import\CrossSource\CrossSourceLinkResult;
use App\Import\EventFamilyResolver;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Parser\Common\SeeTicketsKwankoParser;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;

use function Zenstruck\Foundry\Persistence\save;

final class CrossSourceLinkerTest extends AppKernelTestCase
{
    private Place $venue;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $this->venue = PlaceFactory::createOne(['name' => 'Zénith de Toulouse', 'city' => $city, 'country' => $country]);
    }

    public function testAPreviewWritesNothing(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Claudio capeo');

        [$added, $removed] = $this->link(apply: false);

        self::assertSame([1, 0], [$added, $removed]);
        self::assertSame(0, CrossSourceLinkFactory::count());
        self::assertNull($this->reload($fnac)->getDuplicateOf());
        self::assertNull($this->reload($cdiscount)->getDuplicateOf());
    }

    public function testTheSourcesOfOneShowShareAPage(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Claudio capeo');
        $seeTickets = $this->event(SeeTicketsKwankoParser::getParserName(), 'CLAUDIO CAPEO');

        self::assertSame([3, 0], $this->link());

        self::assertNull($this->reload($fnac)->getDuplicateOf());
        self::assertSame($fnac, $this->reload($cdiscount)->getDuplicateOf()?->getId());
        self::assertSame($fnac, $this->reload($seeTickets)->getDuplicateOf()?->getId());

        // Run again, nothing changes
        self::bootKernel();
        self::assertSame([0, 0], $this->link());
        self::assertSame($fnac, $this->reload($cdiscount)->getDuplicateOf()?->getId());
    }

    public function testAShowItsTitlesDisagreeOnStaysUnlinked(): void
    {
        // The artist alone matches both of their shows of the night
        $this->event(FnacSpectaclesAwinParser::getParserName(), 'Chantal Ladesou - Iconique');
        $this->event(CDiscountAwinParser::getParserName(), 'Chantal ladesou');
        $this->event(SeeTicketsKwankoParser::getParserName(), 'Chantal Ladesou - Forever');

        self::assertSame([0, 0], $this->link());
        self::assertSame(3, EventFactory::count(['duplicateOf' => null]), 'Three pages still');
    }

    public function testAShowItsSourcesNoLongerAgreeOnIsParted(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Claudio capeo');
        $this->link();

        // CDiscount now sells another show under that row
        self::bootKernel();
        $renamed = $this->reload($cdiscount)->setName('Le Lac des Cygnes');
        save($renamed);

        self::assertSame([0, 1], $this->link());
        self::assertSame(0, CrossSourceLinkFactory::count());
        self::assertNull($this->reload($fnac)->getDuplicateOf());
        self::assertNull($this->reload($cdiscount)->getDuplicateOf());
    }

    public function testARowItsSourceTookBackLosesItsLink(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Claudio capeo');
        $this->link();

        self::bootKernel();
        $removed = $this->reload($cdiscount)->markRemovedAtSource();
        save($removed);

        self::assertSame([0, 1], $this->link());
        self::assertNull($this->reload($cdiscount)->getDuplicateOf());
        self::assertNull($this->reload($fnac)->getDuplicateOf());
    }

    public function testAFalseMatchKeptApartIsNeverLinkedAgain(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Claudio capeo');
        $this->link();

        self::bootKernel();
        self::assertSame([$fnac], self::getContainer()->get(CrossSourceLinker::class)->keepApart($cdiscount));

        self::assertNull($this->reload($cdiscount)->getDuplicateOf(), 'Parted at once');
        self::bootKernel();
        self::assertSame([0, 0], $this->link());
        self::assertNull($this->reload($cdiscount)->getDuplicateOf());
        self::assertSame(1, CrossSourceLinkFactory::count(['keptApart' => true]));
    }

    public function testAnEventOverKeepsItsLink(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'Claudio Capéo - Tournée', '-10 days');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'Le Lac des Cygnes', '-10 days');
        CrossSourceLinkFactory::createOne(['event' => $this->reload($fnac), 'linkedEvent' => $this->reload($cdiscount)]);
        self::getContainer()->get(EventFamilyResolver::class)->resolveForEvents([$cdiscount]);

        self::assertSame([0, 0], $this->link());
        self::assertSame(1, CrossSourceLinkFactory::count());
        self::assertSame($fnac, $this->reload($cdiscount)->getDuplicateOf()?->getId());
    }

    /**
     * @return array{int, int} the links made and taken back
     */
    private function link(bool $apply = true): array
    {
        $linker = self::getContainer()->get(CrossSourceLinker::class);
        $added = 0;
        $removed = 0;
        /** @var CrossSourceLinkResult $result */
        foreach ($linker->link([(int) $this->venue->getId()], new DateTimeImmutable('today'), $apply) as $result) {
            $added += $result->added;
            $removed += $result->removed;
        }

        return [$added, $removed];
    }

    private function event(string $source, string $name, string $date = '+10 days'): int
    {
        $event = EventFactory::createOne([
            'fromData' => $source,
            'name' => $name,
            'place' => $this->venue,
            'placeCity' => 'Toulouse',
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($date),
        ]);

        return (int) $event->getId();
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }
}
