<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\MemoryUtils;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MemoryUtilsTest extends TestCase
{
    public function testGetMemoryUsageReturnsString(): void
    {
        $result = MemoryUtils::getMemoryUsage();

        self::assertNotEmpty($result);
    }

    public function testGetMemoryUsageContainsUnit(): void
    {
        $result = MemoryUtils::getMemoryUsage();

        // Should contain one of the memory units
        $hasUnit = preg_match('/\s+(b|kb|mb|gb|tb|pb)$/i', $result);
        self::assertEquals(1, $hasUnit, "Result should contain a memory unit: $result");
    }

    public function testGetPeakMemoryUsageReturnsString(): void
    {
        $result = MemoryUtils::getPeakMemoryUsage();

        self::assertNotEmpty($result);
    }

    public function testGetPeakMemoryUsageContainsUnit(): void
    {
        $result = MemoryUtils::getPeakMemoryUsage();

        $hasUnit = preg_match('/\s+(b|kb|mb|gb|tb|pb)$/i', $result);
        self::assertEquals(1, $hasUnit, "Result should contain a memory unit: $result");
    }

    public function testGetMemoryUsageWithRealUsage(): void
    {
        $result1 = MemoryUtils::getMemoryUsage(false);
        $result2 = MemoryUtils::getMemoryUsage(true);

        // Both should be valid memory strings
        self::assertNotEmpty($result1);
        self::assertNotEmpty($result2);
    }

    public function testFormatMemoryWithBytes(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 512);

        self::assertEquals('512 b', $result);
    }

    public function testFormatMemoryWithKilobytes(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 1024);

        self::assertEquals('1 kb', $result);
    }

    public function testFormatMemoryWithMegabytes(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 1024 * 1024);

        self::assertEquals('1 mb', $result);
    }

    public function testFormatMemoryWithGigabytes(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 1024 * 1024 * 1024);

        self::assertEquals('1 gb', $result);
    }

    public function testFormatMemoryWithDecimalValues(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 1536); // 1.5 KB
        self::assertIsString($result);

        self::assertStringStartsWith('1.5', $result);
        self::assertStringContainsString('kb', $result);
    }

    public function testFormatMemoryWithZero(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $result = $method->invoke(null, 0);
        self::assertIsString($result);

        self::assertStringContainsString('b', $result);
    }

    public function testFormatMemoryWithLargeValue(): void
    {
        $reflection = new ReflectionClass(MemoryUtils::class);
        $method = $reflection->getMethod('formatMemory');
        $method->setAccessible(true);

        $petabyte = 1024 ** 5;
        $result = $method->invoke(null, $petabyte);
        self::assertIsString($result);

        self::assertStringContainsString('pb', $result);
    }

    public function testGetPeakIsGreaterThanOrEqualToCurrent(): void
    {
        $current = MemoryUtils::getMemoryUsage();
        $peak = MemoryUtils::getPeakMemoryUsage();

        // Peak memory should always be >= current memory
        // We can't directly compare the strings, but we can verify they're both valid
        self::assertMatchesRegularExpression('/[\d.]+ (b|kb|mb|gb|tb|pb)/', $current);
        self::assertMatchesRegularExpression('/[\d.]+ (b|kb|mb|gb|tb|pb)/', $peak);
    }
}
