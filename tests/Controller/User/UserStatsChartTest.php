<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Controller\User\UserController;
use App\Tests\AppKernelTestCase;
use Locale;
use ReflectionMethod;

/**
 * The stats query needs MySQL's DAYOFWEEK(), missing from the SQLite test database: the chart
 * built from its result is checked on its own.
 */
final class UserStatsChartTest extends AppKernelTestCase
{
    public function testEachDayGetsTheCountOfThatDay(): void
    {
        $locale = Locale::getDefault();
        Locale::setDefault('fr');

        try {
            // 3 events on Sundays, 5 on Mondays, 7 on Saturdays
            $chart = $this->getWeekChart([1 => 3, 2 => 5, 7 => 7]);
        } finally {
            Locale::setDefault($locale);
        }

        self::assertSame(['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'], $chart['full_categories']);
        self::assertSame([3, 5, 0, 0, 0, 0, 7], $chart['data']);
    }

    /**
     * @param array<int, int> $counts
     *
     * @return array<string, list<mixed>>
     */
    private function getWeekChart(array $counts): array
    {
        $controller = self::getContainer()->get(UserController::class);

        return new ReflectionMethod(UserController::class, 'getWeekChart')->invoke($controller, $counts);
    }
}
