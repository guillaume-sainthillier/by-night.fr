<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Routing;

use App\Routing\LocationRequirement;
use App\Tests\AppKernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * "/suisse/geneve" is a city under its country only because no route of a location starts with "geneve": a route
 * added under "/{location}/carte" would read as the city "suisse/carte" unless "carte" is reserved.
 */
final class LocationRequirementTest extends AppKernelTestCase
{
    public function testTheFirstWordOfEveryRouteOfALocationIsReserved(): void
    {
        $words = [];
        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (1 === preg_match('#^/\{location\}/([^/{]+)#', $route->getPath(), $matches)) {
                $words[$matches[1]][] = $name;
            }
        }

        self::assertNotEmpty($words);
        foreach ($words as $word => $routes) {
            self::assertTrue(LocationRequirement::isReserved($word), \sprintf('"%s" (%s) must be in LocationRequirement::RESERVED and PATTERN', $word, implode(', ', $routes)));
            self::assertDoesNotMatchRegularExpression('#^(?:' . LocationRequirement::PATTERN . ')$#', 'suisse/' . $word, \sprintf('PATTERN must exclude "%s"', $word));
        }
    }
}
