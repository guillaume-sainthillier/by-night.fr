<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PageFactory;
use App\Factory\UserFactory;
use App\Message\PurgeCdnCachePrefix;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use League\Flysystem\FilesystemOperator;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

use function Zenstruck\Foundry\Persistence\save;

/**
 * Picasso 1.x rendered every upload under glide/vich/. After the upgrade only event images still live there
 * (event_image's URL alias): the 1.x thumbnails of the other uploads go, with their Cloudflare prefix; event
 * thumbnails and the 2.0 directories stay.
 */
final class ImagesRemoveLegacyThumbnailsCommandTest extends AppKernelTestCase
{
    /** @var list<string> uploads that are not event images, relative to their storage */
    private array $legacyPaths;

    private string $eventPath;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $createdAt = new DateTimeImmutable('2017-11-27');

        $user = UserFactory::createOne(['createdAt' => $createdAt]);
        $user->getImage()->setName('avatar.jpg');
        $user->getImageSystem()->setName('facebook.jpg');
        save($user);

        $page = PageFactory::createOne(['createdAt' => $createdAt]);
        $page->getImage()->setName('page.jpg');
        save($page);

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $country->getHeroImage()->setName('france.jpg');
        save($country);

        $city = CityFactory::createOne(['name' => 'Lyon', 'country' => $country]);
        $city->getHeroImage()->setName('lyon.jpg');
        save($city);

        // Uploads are filed under their creation date (CurrentDateTimeDirectoryNamer); hero images are not
        $this->legacyPaths = ['2017/11/27/avatar.jpg', '2017/11/27/facebook.jpg', '2017/11/27/page.jpg', 'france.jpg', 'lyon.jpg'];

        $event = EventFactory::createOne(['createdAt' => $createdAt]);
        $event->getImage()->setName('affiche.jpg');
        save($event);
        $this->eventPath = '2017/11/27/affiche.jpg';

        foreach ([...$this->legacyPaths, $this->eventPath] as $path) {
            $this->thumbs()->write(\sprintf('glide/vich/%s/fit_contain,w_360.avif', $path), '1.x');
        }
        $this->thumbs()->write('glide/user_image/2017/11/27/avatar.jpg/fit_contain,w_360.avif', '2.0');
    }

    public function testPreviewCountsWithoutDeleting(): void
    {
        $tester = $this->doRunCommand([]);

        self::assertStringContainsString('5 uploads would have their 1.x thumbnails deleted', $tester->getDisplay());
        foreach ($this->legacyPaths as $path) {
            self::assertTrue($this->thumbs()->fileExists(\sprintf('glide/vich/%s/fit_contain,w_360.avif', $path)));
        }
        self::assertSame([], $this->transport()->getSent());
    }

    public function testDeletesThe1xThumbnailsOfEveryUploadButEventImages(): void
    {
        $this->doRunCommand(['--apply' => true]);

        foreach ($this->legacyPaths as $path) {
            self::assertFalse($this->thumbs()->fileExists(\sprintf('glide/vich/%s/fit_contain,w_360.avif', $path)), $path);
        }
        self::assertTrue($this->thumbs()->fileExists(\sprintf('glide/vich/%s/fit_contain,w_360.avif', $this->eventPath)), 'event thumbnails are current');
        self::assertTrue($this->thumbs()->fileExists('glide/user_image/2017/11/27/avatar.jpg/fit_contain,w_360.avif'), '2.0 thumbnails stay');

        self::assertEqualsCanonicalizing(
            array_map(static fn (string $path): PurgeCdnCachePrefix => new PurgeCdnCachePrefix('by-night.fr/p/image/glide/vich/' . $path . '/'), $this->legacyPaths),
            array_map(static fn ($envelope): object => $envelope->getMessage(), $this->transport()->getSent()),
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $this->transport()->reset();

        $tester = new CommandTester(new Application(self::$kernel)->find('app:images:remove-legacy-thumbnails'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }

    private function thumbs(): FilesystemOperator
    {
        return self::getContainer()->get('thumbs.storage');
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
