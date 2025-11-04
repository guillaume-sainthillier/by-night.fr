<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\ChunkUtils;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use stdClass;

class ChunkUtilsTest extends TestCase
{
    public function testGetChunksByClassWithSingleType(): void
    {
        $objects = [
            new stdClass(),
            new stdClass(),
            new stdClass(),
        ];

        $result = ChunkUtils::getChunksByClass($objects);

        self::assertCount(1, $result);
        self::assertArrayHasKey(stdClass::class, $result);
        self::assertCount(3, $result[stdClass::class]);
    }

    public function testGetChunksByClassWithMultipleTypes(): void
    {
        $obj1 = new stdClass();
        $obj2 = new ArrayObject();
        $obj3 = new stdClass();
        $obj4 = new ArrayObject();

        $objects = [$obj1, $obj2, $obj3, $obj4];
        $result = ChunkUtils::getChunksByClass($objects);

        self::assertCount(2, $result);
        self::assertCount(2, $result[stdClass::class]);
        self::assertCount(2, $result[ArrayObject::class]);
    }

    public function testGetChunksByClassPreservesKeys(): void
    {
        $objects = [
            0 => new stdClass(),
            5 => new stdClass(),
            10 => new stdClass(),
        ];

        $result = ChunkUtils::getChunksByClass($objects);

        self::assertArrayHasKey(0, $result[stdClass::class]);
        self::assertArrayHasKey(5, $result[stdClass::class]);
        self::assertArrayHasKey(10, $result[stdClass::class]);
    }

    public function testGetChunksByClassWithEmptyArray(): void
    {
        $result = ChunkUtils::getChunksByClass([]);

        self::assertEmpty($result);
    }

    public function testGetNestedChunksByClassWithSingleChunk(): void
    {
        $objects = [
            new stdClass(),
            new stdClass(),
        ];

        $result = ChunkUtils::getNestedChunksByClass($objects, 5);

        self::assertCount(1, $result);
        $classResult = $result[stdClass::class];
        self::assertIsArray($classResult);
        self::assertCount(1, $classResult);
        $chunk = $classResult[0];
        self::assertIsArray($chunk);
        self::assertCount(2, $chunk);
    }

    public function testGetNestedChunksByClassWithMultipleChunks(): void
    {
        $objects = [
            new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass(),
            new stdClass(),
        ];

        $result = ChunkUtils::getNestedChunksByClass($objects, 2);

        self::assertCount(1, $result);
        $classResult = $result[stdClass::class];
        self::assertIsArray($classResult);
        self::assertCount(3, $classResult); // 3 chunks: [2, 2, 1]
        $chunk0 = $classResult[0];
        $chunk1 = $classResult[1];
        $chunk2 = $classResult[2];
        self::assertIsArray($chunk0);
        self::assertIsArray($chunk1);
        self::assertIsArray($chunk2);
        self::assertCount(2, $chunk0);
        self::assertCount(2, $chunk1);
        self::assertCount(1, $chunk2);
    }

    public function testGetNestedChunksByClassPreservesKeys(): void
    {
        $objects = [
            0 => new stdClass(),
            1 => new stdClass(),
            2 => new stdClass(),
        ];

        $result = ChunkUtils::getNestedChunksByClass($objects, 2);

        $classResult = $result[stdClass::class];
        self::assertIsArray($classResult);
        $chunk0 = $classResult[0];
        $chunk1 = $classResult[1];
        self::assertIsArray($chunk0);
        self::assertIsArray($chunk1);
        self::assertArrayHasKey(0, $chunk0);
        self::assertArrayHasKey(1, $chunk0);
        self::assertArrayHasKey(2, $chunk1);
    }

    public function testGetNestedChunksByClassWithMixedTypes(): void
    {
        $obj1 = new stdClass();
        $obj2 = new ArrayObject();
        $obj3 = new stdClass();
        $obj4 = new stdClass();

        $objects = [$obj1, $obj2, $obj3, $obj4];
        $result = ChunkUtils::getNestedChunksByClass($objects, 2);

        self::assertCount(2, $result);
        $stdClassResult = $result[stdClass::class];
        $arrayObjectResult = $result[ArrayObject::class];
        self::assertIsArray($stdClassResult);
        self::assertIsArray($arrayObjectResult);
        self::assertCount(2, $stdClassResult); // 2 chunks of stdClass
        self::assertCount(1, $arrayObjectResult); // 1 chunk of ArrayObject
    }

    public function testGetNestedChunksByClassWithChunkSizeOne(): void
    {
        $objects = [
            new stdClass(),
            new stdClass(),
            new stdClass(),
        ];

        $result = ChunkUtils::getNestedChunksByClass($objects, 1);

        $classResult = $result[stdClass::class];
        self::assertIsArray($classResult);
        self::assertCount(3, $classResult);
        $chunk0 = $classResult[0];
        $chunk1 = $classResult[1];
        $chunk2 = $classResult[2];
        self::assertIsArray($chunk0);
        self::assertIsArray($chunk1);
        self::assertIsArray($chunk2);
        self::assertCount(1, $chunk0);
        self::assertCount(1, $chunk1);
        self::assertCount(1, $chunk2);
    }

    public function testGetNestedChunksByClassWithEmptyArray(): void
    {
        $result = ChunkUtils::getNestedChunksByClass([], 2);

        self::assertEmpty($result);
    }
}
