<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Page;
use App\Entity\User;
use App\Tests\AppKernelTestCase;
use App\Twig\ImageExtension;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Silarhi\PicassoBundle\Service\LoaderRegistry;

final class ImageExtensionTest extends AppKernelTestCase
{
    /**
     * upload_loader() names the loader after the field's VichUploader mapping: every mapping needs a Picasso loader
     * of the same name (config/packages/picasso.yaml), or the admin image widgets break for that entity.
     */
    public function testEveryVichMappingHasAPicassoLoaderOfTheSameName(): void
    {
        /** @var array<string, mixed> $mappings */
        $mappings = self::getContainer()->getParameter('vich_uploader.mappings');
        $loaders = self::getContainer()->get('picasso.loader_registry');
        self::assertInstanceOf(LoaderRegistry::class, $loaders);

        self::assertNotEmpty($mappings);
        foreach (array_keys($mappings) as $mapping) {
            self::assertTrue($loaders->has($mapping), \sprintf('the VichUploader mapping "%s" has no Picasso loader', $mapping));
        }
    }

    #[DataProvider('provideUploadFields')]
    public function testNamesTheLoaderOfAnUploadField(object $entity, string $field, string $loader): void
    {
        self::assertSame($loader, self::getContainer()->get(ImageExtension::class)->uploadLoader($entity, $field));
    }

    /**
     * @return iterable<string, array{object, string, string}>
     */
    public static function provideUploadFields(): iterable
    {
        yield 'event image' => [new Event(), 'imageFile', 'event_image'];
        yield 'event source image' => [new Event(), 'imageSystemFile', 'event_image'];
        yield 'user image' => [new User(), 'imageFile', 'user_image'];
        yield 'page image' => [new Page(), 'imageFile', 'page_image'];
        yield 'city hero image' => [new City(), 'heroImageFile', 'city_image'];
        yield 'country hero image' => [new Country(), 'heroImageFile', 'country_image'];
    }

    public function testRejectsAFieldThatIsNoUpload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::getContainer()->get(ImageExtension::class)->uploadLoader(new Event(), 'name');
    }
}
