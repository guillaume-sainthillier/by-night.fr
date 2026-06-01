<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\PlaceNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlaceNameNormalizerTest extends TestCase
{
    private PlaceNameNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PlaceNameNormalizer();
    }

    #[DataProvider('provideNames')]
    public function testNormalize(?string $name, ?string $cityName, ?string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($name, $cityName));
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function provideNames(): iterable
    {
        // Stop words are stripped ("Le"), result is lower-cased.
        yield 'strips leading article' => ['Le Bikini', 'Toulouse', 'bikini'];

        // Case and accents are folded, so variants collapse to the same slug.
        yield 'case-insensitive' => ['LE BIKINI', 'toulouse', 'bikini'];
        yield 'accents folded' => ['Zénith', 'Paris', 'zenith'];

        // The city name is stripped out of the place name first.
        yield 'strips city name' => ['Zénith Paris', 'Paris', 'zenith'];

        // Two surface forms of the same venue normalize identically.
        yield 'variant A' => ['Le Bikini', 'Toulouse', 'bikini'];
        yield 'variant B' => ['Bikini', 'Toulouse', 'bikini'];

        // Degenerate inputs collapse to null (nothing to index on).
        yield 'null name' => [null, 'Toulouse', null];
        yield 'blank name' => ['   ', null, null];
        yield 'only punctuation' => ['@#$%', null, null];
    }
}
