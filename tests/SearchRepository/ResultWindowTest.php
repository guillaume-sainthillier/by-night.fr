<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\SearchRepository\ResultWindow;
use PHPUnit\Framework\TestCase;

final class ResultWindowTest extends TestCase
{
    public function testTheLastPageEndsInsideTheWindow(): void
    {
        // The agenda lists 15 events per page: page 666 ends at 9 990, page 667 at 10 005
        self::assertSame(666, ResultWindow::getMaxPages(15));
        self::assertSame(500, ResultWindow::getMaxPages(20));
        self::assertSame(1, ResultWindow::getMaxPages(50_000));
    }

    public function testPagesPastTheWindowAreOutside(): void
    {
        self::assertTrue(ResultWindow::contains(500, 20));
        self::assertFalse(ResultWindow::contains(501, 20));
        self::assertFalse(ResultWindow::contains(1, 10_001));
    }
}
