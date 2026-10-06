<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\ImageUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImageUrlTest extends TestCase
{
    public static function provideSameImages(): iterable
    {
        yield 'the same URL' => ['https://example.com/a.jpg', 'https://example.com/a.jpg'];
        yield 'no image either time' => [null, null];
        yield 'OpenAgenda moved from cdn. to img.' => [
            'https://cdn.openagenda.com/main/fce403ea3e744fb7982000df7e9ec4ad.full.image.jpg',
            'https://img.openagenda.com/main/fce403ea3e744fb7982000df7e9ec4ad.full.image.jpg',
        ];
        yield 'and back' => [
            'https://img.openagenda.com/main/a.full.image.jpg',
            'https://cdn.openagenda.com/main/a.full.image.jpg',
        ];
    }

    #[DataProvider('provideSameImages')]
    public function testSameImage(?string $url, ?string $otherUrl): void
    {
        self::assertTrue(ImageUrl::isSameImage($url, $otherUrl));
    }

    public static function provideOtherImages(): iterable
    {
        yield 'a first image' => [null, 'https://img.openagenda.com/main/a.full.image.jpg'];
        yield 'an image taken away' => ['https://img.openagenda.com/main/a.full.image.jpg', null];
        yield 'another OpenAgenda file' => [
            'https://cdn.openagenda.com/main/a.full.image.jpg',
            'https://img.openagenda.com/main/b.full.image.jpg',
        ];
        yield 'the same path on hosts sharing nothing' => ['https://a.example.com/a.jpg', 'https://b.example.com/a.jpg'];
        yield 'an OpenAgenda path on another host' => [
            'https://cdn.openagenda.com/main/a.full.image.jpg',
            'https://cibul.s3.amazonaws.com/main/a.full.image.jpg',
        ];
    }

    #[DataProvider('provideOtherImages')]
    public function testOtherImage(?string $url, ?string $otherUrl): void
    {
        self::assertFalse(ImageUrl::isSameImage($url, $otherUrl));
    }
}
