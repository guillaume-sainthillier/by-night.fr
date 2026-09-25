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
use ReflectionProperty;

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

        // The city name is stripped out of the place name.
        yield 'strips city name' => ['Zénith Paris', 'Paris', 'zenith'];
        // With the word tying it to the name: 935 places of the dev base only differed by it
        yield 'strips city name after "de"' => ['Médiathèque de Cugnaux', 'Cugnaux', 'mediatheque'];
        yield 'strips city name after "à"' => ['Théâtre à Toulouse', 'Toulouse', 'theatre'];
        yield "strips city name after \"d'\"" => ["Salle d'Albi", 'Albi', 'salle'];
        yield 'strips city name whatever its case and accents' => ['ZÉNITH DE EVREUX', 'Évreux', 'zenith'];
        yield 'strips city name holding a stop word' => ['Salle de La Rochelle', 'La Rochelle', 'salle'];
        // Only as a whole word: the city of Pau is not in "Paul"
        yield 'keeps a word holding the city name' => ['Salle Paul Éluard', 'Pau', 'salle paul eluard'];

        // Two surface forms of the same venue normalize identically.
        yield 'variant A' => ['Le Bikini', 'Toulouse', 'bikini'];
        yield 'variant B' => ['Bikini', 'Toulouse', 'bikini'];

        // Degenerate inputs collapse to null (nothing to index on).
        yield 'null name' => [null, 'Toulouse', null];
        yield 'blank name' => ['   ', null, null];
        yield 'only punctuation' => ['@#$%', null, null];
    }

    public function testResultsAreMemoizedAndStable(): void
    {
        $first = $this->normalizer->normalize('Le Bikini', 'Toulouse');
        $this->assertNull($this->normalizer->normalize('   '));

        // Push more distinct inputs than the cache holds to exercise its reset path
        for ($i = 0; $i < 10_001; ++$i) {
            $this->normalizer->normalize('Salle ' . $i);
        }

        $this->assertSame('bikini', $first);
        $this->assertSame($first, $this->normalizer->normalize('Le Bikini', 'Toulouse'));
        $this->assertNull($this->normalizer->normalize('   '), 'Null results are memoized as well');
    }

    public function testBatchResetEmptiesTheCache(): void
    {
        $cache = new ReflectionProperty(PlaceNameNormalizer::class, 'cache');

        $this->normalizer->normalize('Le Bikini', 'Toulouse');
        $entries = $cache->getValue($this->normalizer);
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);

        // Cleared with the other per-batch state on EntityManager::clear()
        $this->normalizer->batchReset();

        $this->assertSame([], $cache->getValue($this->normalizer));
        $this->assertSame('bikini', $this->normalizer->normalize('Le Bikini', 'Toulouse'));
    }
}
