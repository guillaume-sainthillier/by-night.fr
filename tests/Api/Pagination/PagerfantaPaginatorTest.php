<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Api\Pagination;

use App\Api\Pagination\PagerfantaPaginator;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PagerfantaPaginatorTest extends TestCase
{
    public function testItListsThePageItemsAsIsWithoutATransformer(): void
    {
        $items = [new stdClass(), new stdClass(), new stdClass()];
        $pagerfanta = new Pagerfanta(new ArrayAdapter($items));
        $pagerfanta->setMaxPerPage(2);

        $paginator = new PagerfantaPaginator($pagerfanta);

        self::assertSame([$items[0], $items[1]], iterator_to_array($paginator, false));
        self::assertSame(3.0, $paginator->getTotalItems());
    }

    public function testItMapsThePageItemsWithATransformer(): void
    {
        $pagerfanta = new Pagerfanta(new ArrayAdapter(['a', 'b', 'c']));
        $pagerfanta->setMaxPerPage(2);

        $paginator = new PagerfantaPaginator($pagerfanta, static function (string $item): stdClass {
            $object = new stdClass();
            $object->name = strtoupper($item);

            return $object;
        });

        self::assertSame(['A', 'B'], array_map(
            static fn (stdClass $object): string => $object->name,
            iterator_to_array($paginator, false),
        ));
    }
}
