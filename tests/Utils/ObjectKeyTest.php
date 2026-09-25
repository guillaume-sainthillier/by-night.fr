<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\ObjectKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ObjectKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideDistinctKeys(): iterable
    {
        yield 'two types, one id' => [ObjectKey::internal('city', 12), ObjectKey::internal('place', 12)];
        yield 'a database id is not a source id' => [ObjectKey::internal('place', 12), ObjectKey::external('place', 'openagenda', '12')];
        yield 'nor data' => [ObjectKey::internal('place', 12), ObjectKey::data('place', '12')];
        yield 'two sources, one id' => [ObjectKey::external('place', 'openagenda', '12'), ObjectKey::external('place', 'datatourisme', '12')];
        yield 'origin and id do not swap' => [ObjectKey::external('place', 'a', 'b'), ObjectKey::external('place', 'b', 'a')];
        yield 'a separator in a name' => [ObjectKey::data('place', 'a-b', 'c'), ObjectKey::data('place', 'a', 'b-c')];
        yield 'a separator in an id' => [ObjectKey::external('event', 'a-b', 'c'), ObjectKey::external('event', 'a', 'b-c')];
        yield 'an empty part still counts' => [ObjectKey::data('city', 'pau', ''), ObjectKey::data('city', 'pau')];
        yield 'a nested key' => [
            ObjectKey::data('place', 'bikini', '', ObjectKey::data('city', 'toulouse', '31000')),
            ObjectKey::data('place', 'bikini', 'rue', ObjectKey::data('city', 'toulouse', '31000')),
        ];
    }

    #[DataProvider('provideDistinctKeys')]
    public function testDifferentPartsNeverShareAKey(string $left, string $right): void
    {
        self::assertNotSame($left, $right);
    }

    public function testTheSamePartsGiveTheSameKey(): void
    {
        self::assertSame(ObjectKey::internal('city', 12), ObjectKey::internal('city', '12'));
        self::assertSame(ObjectKey::data('place', 'bikini', 'rue'), ObjectKey::data('place', 'bikini', 'rue'));
    }

    public function testATransientKeyIsTheObjectsOwn(): void
    {
        $object = new stdClass();

        self::assertSame(ObjectKey::transient('place', $object), ObjectKey::transient('place', $object));
        self::assertNotSame(ObjectKey::transient('place', $object), ObjectKey::transient('place', new stdClass()));
        self::assertNotSame(ObjectKey::transient('place', $object), ObjectKey::internal('place', spl_object_id($object)));
    }
}
