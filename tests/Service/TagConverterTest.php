<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Service;

use App\Service\TagConverter;
use App\Tests\AppKernelTestCase;
use Override;

final class TagConverterTest extends AppKernelTestCase
{
    protected TagConverter $tagConverter;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->tagConverter = self::getContainer()->get(TagConverter::class);
    }

    public function testParseTagStringWithNull(): void
    {
        $result = $this->tagConverter->parseTagString(null);
        self::assertSame([], $result);
    }

    public function testParseTagStringWithEmptyString(): void
    {
        $result = $this->tagConverter->parseTagString('');
        self::assertSame([], $result);
    }

    public function testParseTagStringWithWhitespace(): void
    {
        $result = $this->tagConverter->parseTagString('   ');
        self::assertSame([], $result);
    }

    public function testParseTagStringWithSingleValue(): void
    {
        $result = $this->tagConverter->parseTagString('Concert');
        self::assertSame(['Concert'], $result);
    }

    public function testParseTagStringWithCommaSeparated(): void
    {
        $result = $this->tagConverter->parseTagString('Concert, Musique');
        self::assertSame(['Concert', 'Musique'], $result);
    }

    public function testParseTagStringWithSlashSeparated(): void
    {
        $result = $this->tagConverter->parseTagString('Rock/Jazz');
        self::assertSame(['Rock', 'Jazz'], $result);
    }

    public function testParseTagStringWithMixedSeparators(): void
    {
        $result = $this->tagConverter->parseTagString('Concert, Rock/Jazz, Blues');
        self::assertSame(['Concert', 'Rock', 'Jazz', 'Blues'], $result);
    }

    public function testParseTagStringTrimsWhitespace(): void
    {
        $result = $this->tagConverter->parseTagString('  Concert  ,  Musique  ');
        self::assertSame(['Concert', 'Musique'], $result);
    }

    public function testParseTagStringDeduplicates(): void
    {
        $result = $this->tagConverter->parseTagString('Concert, Concert, Musique');
        self::assertSame(['Concert', 'Musique'], $result);
    }

    public function testParseTagStringWithUnicode(): void
    {
        $result = $this->tagConverter->parseTagString('Théâtre, Opéra, Café-concert');
        self::assertSame(['Théâtre', 'Opéra', 'Café-concert'], $result);
    }

    public function testConvertWithNullInputs(): void
    {
        $result = $this->tagConverter->convert(null, null);

        self::assertNull($result['category']);
        self::assertSame([], $result['themes']);
    }

    public function testConvertWithSingleCategory(): void
    {
        $result = $this->tagConverter->convert('Concert', null);

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        self::assertSame([], $result['themes']);
    }

    public function testConvertWithMultipleCategories(): void
    {
        $result = $this->tagConverter->convert('Concert, Musique', null);

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        self::assertCount(1, $result['themes']);
        self::assertSame('Musique', $result['themes'][0]->getName());
    }

    public function testConvertWithCategoryAndThemes(): void
    {
        $result = $this->tagConverter->convert('Concert', 'Rock, Jazz');

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        self::assertCount(2, $result['themes']);
        self::assertSame('Rock', $result['themes'][0]->getName());
        self::assertSame('Jazz', $result['themes'][1]->getName());
    }

    public function testConvertWithCategoryOverflowAndThemes(): void
    {
        $result = $this->tagConverter->convert('Concert, Musique', 'Rock, Jazz');

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        self::assertCount(3, $result['themes']);
        self::assertSame('Musique', $result['themes'][0]->getName());
        self::assertSame('Rock', $result['themes'][1]->getName());
        self::assertSame('Jazz', $result['themes'][2]->getName());
    }

    public function testConvertDeduplicatesThemes(): void
    {
        $result = $this->tagConverter->convert('Concert, Rock', 'Rock, Jazz');

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        // Rock should appear only once (from category overflow, not duplicated from themes)
        self::assertCount(2, $result['themes']);
        self::assertSame('Rock', $result['themes'][0]->getName());
        self::assertSame('Jazz', $result['themes'][1]->getName());
    }

    public function testConvertExcludesCategoryFromThemes(): void
    {
        // If the category name appears in themes, it should be excluded
        $result = $this->tagConverter->convert('Concert', 'Concert, Jazz');

        self::assertNotNull($result['category']);
        self::assertSame('Concert', $result['category']->getName());
        // Concert should not appear in themes
        self::assertCount(1, $result['themes']);
        self::assertSame('Jazz', $result['themes'][0]->getName());
    }

    public function testConvertWithOnlyThemes(): void
    {
        $result = $this->tagConverter->convert(null, 'Rock, Jazz');

        self::assertNull($result['category']);
        self::assertCount(2, $result['themes']);
        self::assertSame('Rock', $result['themes'][0]->getName());
        self::assertSame('Jazz', $result['themes'][1]->getName());
    }

    public function testConvertReusesSameTagEntity(): void
    {
        // First conversion creates the tag
        $result1 = $this->tagConverter->convert('Concert', null);

        // Second conversion should reuse the same tag
        $result2 = $this->tagConverter->convert('Concert', null);

        self::assertSame($result1['category'], $result2['category']);
    }

    public function testConvertCaseInsensitiveDeduplication(): void
    {
        // Category and theme with same name but different case
        $result = $this->tagConverter->convert('Concert', 'concert, Jazz');

        self::assertNotNull($result['category']);
        // 'concert' (lowercase) should be excluded as it matches category
        self::assertCount(1, $result['themes']);
        self::assertSame('Jazz', $result['themes'][0]->getName());
    }
}
