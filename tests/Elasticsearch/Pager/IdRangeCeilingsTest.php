<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Elasticsearch\Pager;

use App\Elasticsearch\Pager\IdRangeCeilings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class IdRangeCeilingsTest extends TestCase
{
    public function testEveryProcessOfAPopulateSharesTheCeilingReadFirst(): void
    {
        $ceilings = new IdRangeCeilings(new ArrayAdapter());
        $highestId = 100;

        self::assertSame(100, $ceilings->get('event', static fn (): int => $highestId));
        // Events created while the workers handle the pages
        $highestId = 150;
        self::assertSame(100, $ceilings->get('event', static fn (): int => $highestId));
    }

    public function testAForgottenCeilingIsReadAgain(): void
    {
        $ceilings = new IdRangeCeilings(new ArrayAdapter());
        $ceilings->get('event', static fn (): int => 100);
        $ceilings->get('city', static fn (): int => 30);

        $ceilings->forget('event');

        self::assertSame(150, $ceilings->get('event', static fn (): int => 150));
        self::assertSame(30, $ceilings->get('city', static fn (): int => 40));
    }
}
