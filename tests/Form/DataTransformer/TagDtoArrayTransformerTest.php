<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Form\DataTransformer;

use App\Dto\TagDto;
use App\Form\DataTransformer\TagDtoArrayTransformer;
use App\Form\DataTransformer\TagDtoTransformer;
use PHPUnit\Framework\TestCase;

/**
 * The themes of the event form are one text field of comma-separated names.
 */
final class TagDtoArrayTransformerTest extends TestCase
{
    public function testThemesAreShownAsCommaSeparatedNames(): void
    {
        $transformer = new TagDtoArrayTransformer();

        self::assertSame('Jazz,Concert', $transformer->transform([TagDto::fromString('Jazz'), TagDto::fromString('Concert')]));
        self::assertNull($transformer->transform([]));
        self::assertNull($transformer->transform(null));
    }

    public function testTypedNamesBecomeThemes(): void
    {
        self::assertSame(['Jazz', 'Concert'], self::names(new TagDtoArrayTransformer()->reverseTransform(' Jazz , Concert ,, ')));
        self::assertSame([], new TagDtoArrayTransformer()->reverseTransform('  '));
        self::assertSame([], new TagDtoArrayTransformer()->reverseTransform(null));
    }

    /**
     * DataTourisme names some themes with a comma ("Peintures, arts graphiques", 575 events of
     * the dev base): the field and its tags widget split on commas, so saving the edit form of
     * such an event, themes untouched, made two themes of it.
     */
    public function testAThemeWithACommaStaysOneTheme(): void
    {
        $transformer = new TagDtoArrayTransformer();
        $themes = [TagDto::fromString('Peintures, arts graphiques'), TagDto::fromString('Jazz')];

        $shown = $transformer->transform($themes);

        self::assertCount(2, explode(',', (string) $shown), 'The widget shows two themes');
        self::assertSame(['Peintures, arts graphiques', 'Jazz'], self::names($transformer->reverseTransform($shown)));
    }

    public function testTheCategoryIsOneName(): void
    {
        $transformer = new TagDtoTransformer();

        self::assertSame('Jazz', $transformer->transform(TagDto::fromString('Jazz')));
        self::assertNull($transformer->transform(null));
        self::assertSame('Jazz', $transformer->reverseTransform(' Jazz ')?->name);
        self::assertNull($transformer->reverseTransform(' '));
    }

    /**
     * @param TagDto[] $tags
     *
     * @return list<string|null>
     */
    private static function names(array $tags): array
    {
        return array_values(array_map(static fn (TagDto $tag): ?string => $tag->name, $tags));
    }
}
