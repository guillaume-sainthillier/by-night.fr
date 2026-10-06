<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import\CrossSource;

use App\Import\CrossSource\CrossSourceGroup;
use App\Import\CrossSource\CrossSourceGrouper;
use App\Import\CrossSource\CrossSourcePair;
use App\Import\CrossSource\EventTitleComparator;
use App\Import\CrossSource\MatchVerdict;
use PHPUnit\Framework\TestCase;

final class CrossSourceGrouperTest extends TestCase
{
    public function testThePairsOfOneShowMakeOneGroup(): void
    {
        $groups = $this->group([
            $this->pair(1, 'Fnac Spectacles', 'Claudio Capéo - Tournée', 2, 'CDiscount', 'Claudio capeo'),
            $this->pair(2, 'CDiscount', 'Claudio capeo', 3, 'SeeTickets', 'CLAUDIO CAPEO'),
        ]);

        self::assertCount(1, $groups);
        self::assertSame([1, 2, 3], array_keys($groups[0]->members));
        self::assertTrue($groups[0]->isConsistent());
        self::assertFalse($groups[0]->hasSeveralEventsOfOneSource());
    }

    public function testATitleNamingTheArtistAloneDoesNotChainTwoShows(): void
    {
        $groups = $this->group([
            $this->pair(1, 'Fnac Spectacles', 'Chantal Ladesou - Iconique', 2, 'CDiscount', 'Chantal ladesou'),
            $this->pair(2, 'CDiscount', 'Chantal ladesou', 3, 'Fnac Spectacles', 'Chantal Ladesou - Forever'),
        ]);

        self::assertCount(1, $groups);
        self::assertFalse($groups[0]->isConsistent());
        self::assertSame([[1, 3]], $groups[0]->conflicts);
    }

    public function testThePairsTheMatcherTurnedDownAreLeftOut(): void
    {
        $groups = $this->group([
            $this->pair(1, 'Fnac Spectacles', 'Florent Pagny', 2, 'CDiscount', 'Florent pagny', MatchVerdict::OtherDay),
        ]);

        self::assertSame([], $groups);
    }

    /**
     * @param list<CrossSourcePair> $pairs
     *
     * @return list<CrossSourceGroup>
     */
    private function group(array $pairs): array
    {
        return new CrossSourceGrouper(new EventTitleComparator())->group($pairs);
    }

    private function pair(int $leftId, string $leftSource, string $leftName, int $rightId, string $rightSource, string $rightName, MatchVerdict $verdict = MatchVerdict::Same): CrossSourcePair
    {
        return new CrossSourcePair($leftId, $leftSource, $leftName, $rightId, $rightSource, $rightName, 'Zénith de Toulouse', 'Toulouse', null, $verdict);
    }
}
