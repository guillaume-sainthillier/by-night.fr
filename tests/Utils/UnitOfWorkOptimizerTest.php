<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\UnitOfWorkOptimizer;
use PHPUnit\Framework\TestCase;

final class UnitOfWorkOptimizerTest extends TestCase
{
    public function testNullAndAnEmptyArrayAreStoredTheSame(): void
    {
        self::assertSame([], UnitOfWorkOptimizer::getArrayValue([], null), 'a "simple_array" row loads NULL as []');
        self::assertNull(UnitOfWorkOptimizer::getArrayValue(null, []));
    }

    public function testAnEqualArrayKeepsTheOriginal(): void
    {
        self::assertSame(['01 23 45 67 89'], UnitOfWorkOptimizer::getArrayValue(['01 23 45 67 89'], ['01 23 45 67 89']));
    }

    public function testAChangedArrayIsTaken(): void
    {
        self::assertSame([800, 600], UnitOfWorkOptimizer::getArrayValue([640, 480], [800, 600]));
        self::assertSame(['a@b.c'], UnitOfWorkOptimizer::getArrayValue(null, ['a@b.c']));
        self::assertNull(UnitOfWorkOptimizer::getArrayValue(['a@b.c'], null));
    }

    public function testTheTypesOfTheValuesCount(): void
    {
        // A "simple_array" caller casts to strings first: getArrayValue() stays as strict as Doctrine
        self::assertSame([800, 600], UnitOfWorkOptimizer::getArrayValue(['800', '600'], [800, 600]));
    }

    public function testTheOrderOfTheValuesCounts(): void
    {
        // Image dimensions are [width, height]
        self::assertSame([600, 800], UnitOfWorkOptimizer::getArrayValue([800, 600], [600, 800]));
    }
}
