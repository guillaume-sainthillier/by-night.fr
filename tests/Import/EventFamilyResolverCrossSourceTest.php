<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Enum\DuplicateReason;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\CrossSourceLinkFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Import\EventFamilyResolver;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\DataTourismeParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Parser\Common\OpenAgendaParser;
use App\Parser\Common\SeeTicketsKwankoParser;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;

use function Zenstruck\Foundry\Persistence\delete;
use function Zenstruck\Foundry\Persistence\save;

/**
 * Families gathered across sources by a CrossSourceLink, beside those of one source (EventFamilyResolverTest).
 */
final class EventFamilyResolverCrossSourceTest extends AppKernelTestCase
{
    private EventFamilyResolver $resolver;

    private Place $place;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = self::getContainer()->get(EventFamilyResolver::class);

        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $this->place = PlaceFactory::createOne(['name' => 'Zénith', 'city' => $city, 'country' => $country]);
        $this->user = UserFactory::createOne();
    }

    public function testTwoSourcesOfOneShowShareAPage(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-05');
        $this->link($fnac, $cdiscount);

        $this->resolver->resolveForEvents([$cdiscount]);

        $canonical = $this->reload($fnac);
        $duplicate = $this->reload($cdiscount);
        self::assertNull($canonical->getDuplicateOf());
        self::assertSame($fnac, $duplicate->getDuplicateOf()?->getId());
        self::assertSame(DuplicateReason::SameShow, $duplicate->getDuplicateReason());

        // The canonical shows both dates
        self::assertSame(['2026-12-04' => null, '2026-12-05' => $cdiscount], $this->datesBySource($canonical));
        self::assertSame('2026-12-05', $canonical->getEndDate()?->format('Y-m-d'));
    }

    public function testAnotherSourceDoesNotRepeatADayThePageShows(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04', '20:00');
        $seeTickets = $this->event(SeeTicketsKwankoParser::getParserName(), 'seetickets-hash', '2026-12-04', null, 'À 20h');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-04');
        $matinee = $this->event(CDiscountAwinParser::getParserName(), 'matinee-hash', '2026-12-04', '15:00');
        $this->link($fnac, $seeTickets);
        $this->link($fnac, $cdiscount);
        $this->link($fnac, $matinee);

        $this->resolver->resolveForEvents([$fnac]);

        // The same evening said three ways is shown once; the matinee is another session
        $sessions = array_map(
            static fn ($timesheet): array => [$timesheet->getStartAt()?->format('Y-m-d'), $timesheet->getStartTime()?->format('H:i'), $timesheet->getSourceEvent()?->getId()],
            $this->reload($fnac)->getSessions(),
        );
        usort($sessions, static fn (array $a, array $b): int => [$a[1], $a[2]] <=> [$b[1], $b[2]]);
        self::assertSame([['2026-12-04', '15:00', $matinee], ['2026-12-04', '20:00', null]], $sessions);
    }

    public function testTheSourceShowingTheShowBestKeepsThePage(): void
    {
        // Older, but CDiscount titles its shows in lower case
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-04');
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04');
        $openAgenda = $this->event(OpenAgendaParser::getParserName(), 'oa-hash', '2026-12-04');
        $this->link($cdiscount, $fnac);
        $this->link($fnac, $openAgenda);

        $this->resolver->resolveForEvents([$cdiscount]);

        self::assertNull($this->reload($openAgenda)->getDuplicateOf(), 'An agenda writes a description');
        self::assertSame($openAgenda, $this->reload($fnac)->getDuplicateOf()?->getId());
        self::assertSame($openAgenda, $this->reload($cdiscount)->getDuplicateOf()?->getId(), 'Every member points at the canonical');
    }

    public function testACanonicalKeepsItsPageOnceElected(): void
    {
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-04');
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04');
        $this->link($cdiscount, $fnac);
        $this->resolver->resolveForEvents([$fnac]);

        // A third source joins: the page already public stays where it is
        $openAgenda = $this->event(OpenAgendaParser::getParserName(), 'oa-hash', '2026-12-04');
        $this->link($fnac, $openAgenda);
        $this->resolver->resolveForEvents([$openAgenda]);

        self::assertNull($this->reload($fnac)->getDuplicateOf());
        self::assertSame($fnac, $this->reload($openAgenda)->getDuplicateOf()?->getId());
    }

    public function testAFamilyOfOneSourceJoinsTheShowAsAWhole(): void
    {
        // Fnac lists the show twice under one identity, CDiscount once
        $first = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04');
        $second = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-05');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-04');
        $this->link($second, $cdiscount);

        $this->resolver->resolveForEvents([$cdiscount]);

        self::assertNull($this->reload($first)->getDuplicateOf());
        self::assertSame(DuplicateReason::SameIdentity, $this->reload($second)->getDuplicateReason());
        self::assertSame($first, $this->reload($cdiscount)->getDuplicateOf()?->getId());
        self::assertSame(['2026-12-04' => null, '2026-12-05' => $second], $this->datesBySource($this->reload($first)), 'One date, lent once');

        // Importing one of them again leaves the family as it is
        $this->resolver->resolveForEvents([$second]);

        self::assertNull($this->reload($first)->getDuplicateOf());
        self::assertSame($first, $this->reload($second)->getDuplicateOf()?->getId());
        self::assertSame($first, $this->reload($cdiscount)->getDuplicateOf()?->getId());
    }

    public function testAShowItsSourcesNoLongerAgreeOnIsParted(): void
    {
        $fnac = $this->event(FnacSpectaclesAwinParser::getParserName(), 'fnac-hash', '2026-12-04');
        $cdiscount = $this->event(CDiscountAwinParser::getParserName(), 'cdiscount-hash', '2026-12-05');
        $link = $this->link($fnac, $cdiscount);
        $this->resolver->resolveForEvents([$cdiscount]);

        delete($link);
        $this->resolver->resolveForEvents([$fnac, $cdiscount]);

        $canonical = $this->reload($fnac);
        self::assertNull($this->reload($cdiscount)->getDuplicateOf(), 'Both pages are back');
        self::assertNull($this->reload($cdiscount)->getDuplicateReason());
        self::assertSame(['2026-12-04' => null], $this->datesBySource($canonical), 'Without the dates it lent');
        self::assertSame('2026-12-04', $canonical->getEndDate()?->format('Y-m-d'));
    }

    public function testARowWithoutIdentityIsPartedToo(): void
    {
        // DATAtourisme names no venue: its events have no identity hash
        $openAgenda = $this->event(OpenAgendaParser::getParserName(), 'oa-hash', '2026-12-04');
        $dataTourisme = $this->event(DataTourismeParser::getParserName(), null, '2026-12-04');
        $link = $this->link($openAgenda, $dataTourisme);
        $this->resolver->resolveForEvents([$dataTourisme]);
        self::assertSame($openAgenda, $this->reload($dataTourisme)->getDuplicateOf()?->getId());

        delete($link);
        $this->resolver->resolveForEvents([$dataTourisme]);

        self::assertNull($this->reload($dataTourisme)->getDuplicateOf());
    }

    public function testALinkMadeByHandIsLeftAsItIs(): void
    {
        $openAgenda = $this->event(OpenAgendaParser::getParserName(), 'oa-hash', '2026-12-04');
        $dataTourisme = $this->event(DataTourismeParser::getParserName(), null, '2026-12-04');
        $byHand = $this->reload($dataTourisme);
        $byHand->setDuplicateOf($this->reload($openAgenda));
        save($byHand);

        $this->resolver->resolveForEvents([$dataTourisme, $openAgenda]);

        self::assertSame($openAgenda, $this->reload($dataTourisme)->getDuplicateOf()?->getId());
        self::assertNull($this->reload($dataTourisme)->getDuplicateReason());
        self::assertSame(['2026-12-04' => null], $this->datesBySource($this->reload($openAgenda)), 'It lends no date');
    }

    private function event(string $source, ?string $hash, string $date, ?string $startTime = null, ?string $hours = null): int
    {
        $event = EventFactory::createOne([
            'fromData' => $source,
            'identityHash' => $hash,
            'name' => 'Claudio Capéo',
            'place' => $this->place,
            'user' => $this->user,
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($date),
        ]);
        EventTimesheetFactory::new()->on($date, $hours)->create([
            'event' => $event,
            'startTime' => null !== $startTime ? new DateTimeImmutable($startTime) : null,
        ]);

        return (int) $event->getId();
    }

    private function link(int $leftId, int $rightId): object
    {
        return CrossSourceLinkFactory::createOne([
            'event' => $this->reload(min($leftId, $rightId)),
            'linkedEvent' => $this->reload(max($leftId, $rightId)),
        ]);
    }

    private function reload(int $id): Event
    {
        return EventFactory::find(['id' => $id]);
    }

    /**
     * @return array<string, int|null>
     */
    private function datesBySource(Event $event): array
    {
        $dates = [];
        foreach ($event->getTimesheets() as $timesheet) {
            $dates[(string) $timesheet->getStartAt()?->format('Y-m-d')] = $timesheet->getSourceEvent()?->getId();
        }

        ksort($dates);

        return $dates;
    }
}
