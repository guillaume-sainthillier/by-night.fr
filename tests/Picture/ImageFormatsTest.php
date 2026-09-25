<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Picture;

use App\Dto\EventDto;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\User;
use App\Tests\AppKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Uploads are raster images: an SVG may carry script, which would run when opened from the
 * storage domain.
 */
final class ImageFormatsTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function provideUploadFields(): iterable
    {
        yield 'event form' => [EventDto::class, 'imageFile'];
        yield 'event image' => [Event::class, 'imageFile'];
        yield 'event system image' => [Event::class, 'imageSystemFile'];
        yield 'member picture' => [User::class, 'imageFile'];
        yield 'member system picture' => [User::class, 'imageSystemFile'];
        yield 'page image' => [Page::class, 'imageFile'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideUploadFields')]
    public function testAnSvgIsRefused(string $class, string $field): void
    {
        $svg = $this->file('svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>');

        self::assertCount(1, $this->validator()->validatePropertyValue($class, $field, $svg));
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideUploadFields')]
    public function testRasterImagesAreAccepted(string $class, string $field): void
    {
        foreach (['png' => imagepng(...), 'webp' => imagewebp(...), 'jpeg' => imagejpeg(...)] as $extension => $write) {
            $image = imagecreatetruecolor(10, 10);
            ob_start();
            $write($image);

            self::assertCount(0, $this->validator()->validatePropertyValue($class, $field, $this->file($extension, (string) ob_get_clean())), $extension);
        }
    }

    private function file(string $extension, string $content): File
    {
        $path = \sprintf('%s/%s.%s', sys_get_temp_dir(), uniqid('upload-', true), $extension);
        file_put_contents($path, $content);

        return new File($path);
    }

    private function validator(): ValidatorInterface
    {
        return self::getContainer()->get(ValidatorInterface::class);
    }
}
