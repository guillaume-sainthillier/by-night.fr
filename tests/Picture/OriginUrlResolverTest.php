<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Picture;

use App\Picture\OriginUrlResolver;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Cache\CacheInterface;

final class OriginUrlResolverTest extends TestCase
{
    private const string EVENT_IMAGE = '2026/05/30/b60ced9db3974e66aba213cb212c1950-full-image-6a1a6515be8ac690031817.jpg';

    private const string USER_IMAGE = '2017/11/27/5aa78efdc33f4482315163.jpg';

    private Filesystem $events;

    private Filesystem $users;

    private Filesystem $pages;

    private CacheInterface $cache;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->events = new Filesystem(new InMemoryFilesystemAdapter());
        $this->users = new Filesystem(new InMemoryFilesystemAdapter());
        $this->pages = new Filesystem(new InMemoryFilesystemAdapter());
        $this->cache = new ArrayAdapter();

        $this->projectDir = sys_get_temp_dir() . '/by-night-origin-' . uniqid();
        mkdir($this->projectDir . '/public/build/images', 0777, true);
        file_put_contents($this->projectDir . '/public/build/images/logo.png', 'png');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function testResolvesAnEventImageToTheDocumentsPrefix(): void
    {
        $this->events->write(self::EVENT_IMAGE, 'jpg');

        self::assertSame(
            'https://data.by-night.fr/uploads/documents/' . self::EVENT_IMAGE,
            $this->createResolver()->resolve('vich', self::EVENT_IMAGE),
        );
    }

    public function testResolvesAUserImageEvenThoughEventsAreProbedFirst(): void
    {
        $this->users->write(self::USER_IMAGE, 'jpg');

        self::assertSame(
            'https://data.by-night.fr/uploads/users/' . self::USER_IMAGE,
            $this->createResolver()->resolve('vich', self::USER_IMAGE),
        );
    }

    /**
     * Ordering is what keeps the common case down to a single probe, so pin it: with
     * the same path in two storages, the events one wins.
     */
    public function testProbesEventsBeforeUsers(): void
    {
        $this->events->write(self::EVENT_IMAGE, 'jpg');
        $this->users->write(self::EVENT_IMAGE, 'jpg');

        self::assertStringContainsString(
            '/uploads/documents/',
            (string) $this->createResolver()->resolve('vich', self::EVENT_IMAGE),
        );
    }

    public function testReturnsNullWhenNoStorageHoldsTheFile(): void
    {
        self::assertNull($this->createResolver()->resolve('vich', self::EVENT_IMAGE));
    }

    /**
     * A well-formed public-cache URL carries a params segment after the source path.
     * It has to be stripped, or the probe would look for a file that never existed.
     */
    public function testStripsThePublicCacheParamsSegment(): void
    {
        $this->events->write(self::EVENT_IMAGE, 'jpg');

        self::assertSame(
            'https://data.by-night.fr/uploads/documents/' . self::EVENT_IMAGE,
            $this->createResolver()->resolve('vich', self::EVENT_IMAGE . '/fit_contain,fm_jpg,h_253,w_360.jpg'),
        );
    }

    /**
     * A file name made of an underscore and a dot must not be mistaken for a params
     * segment: stripping it would send the visitor to the wrong file.
     */
    public function testKeepsAnOrdinaryFileNameThatLooksLikeAParamsSegment(): void
    {
        $this->events->write('2026/05/30/my_photo.jpg', 'jpg');

        self::assertSame(
            'https://data.by-night.fr/uploads/documents/2026/05/30/my_photo.jpg',
            $this->createResolver()->resolve('vich', '2026/05/30/my_photo.jpg'),
        );
    }

    public function testResolvesAPublicBuildAssetWithoutProbingAnyStorage(): void
    {
        self::assertSame(
            '/build/images/logo.png',
            $this->createResolver()->resolve('filesystem', 'build/images/logo.png'),
        );
    }

    public function testReturnsNullForAStaleBuildAsset(): void
    {
        self::assertNull($this->createResolver()->resolve('filesystem', 'build/images/gone.png'));
    }

    public function testRejectsATraversingPath(): void
    {
        self::assertNull($this->createResolver()->resolve('filesystem', 'build/../../.env'));
    }

    public function testReturnsNullForAnUnknownLoader(): void
    {
        self::assertNull($this->createResolver()->resolve('imgix', self::EVENT_IMAGE));
    }

    /**
     * The probe result is cached, so a second lookup must not touch the storages —
     * that is the whole point, since a handful of paths make up most of the traffic.
     */
    public function testCachesTheResolution(): void
    {
        $this->events->write(self::EVENT_IMAGE, 'jpg');

        $resolver = $this->createResolver();
        $first = $resolver->resolve('vich', self::EVENT_IMAGE);

        $this->events->delete(self::EVENT_IMAGE);

        self::assertSame($first, $resolver->resolve('vich', self::EVENT_IMAGE));
    }

    /**
     * One unreachable storage must not sink the whole lookup: the probe carries on
     * down the list, so a file held further along still resolves.
     */
    public function testKeepsProbingAfterAStorageErrors(): void
    {
        $this->users->write(self::USER_IMAGE, 'jpg');

        self::assertSame(
            'https://data.by-night.fr/uploads/users/' . self::USER_IMAGE,
            $this->createResolver(events: $this->createFailingStorage())->resolve('vich', self::USER_IMAGE),
        );
    }

    public function testReturnsNullWhenEveryProbeErrors(): void
    {
        $resolver = $this->createResolver(
            events: $this->createFailingStorage(),
            users: $this->createFailingStorage(),
            pages: $this->createFailingStorage(),
        );

        self::assertNull($resolver->resolve('vich', self::EVENT_IMAGE));
    }

    /**
     * Probing stops at the first hit, so the storages further down must never be
     * touched - that is what keeps the S3 clients behind them from being built.
     */
    public function testDoesNotTouchLaterStoragesOnceOneMatches(): void
    {
        $this->events->write(self::EVENT_IMAGE, 'jpg');

        $resolver = $this->createResolver(
            users: $this->createUnusableStorage(),
            pages: $this->createUnusableStorage(),
        );

        self::assertSame(
            'https://data.by-night.fr/uploads/documents/' . self::EVENT_IMAGE,
            $resolver->resolve('vich', self::EVENT_IMAGE),
        );
    }

    private function createFailingStorage(): FilesystemOperator
    {
        $storage = self::createStub(FilesystemOperator::class);
        $storage->method('fileExists')->willThrowException(UnableToCheckExistence::forLocation('boom'));

        return $storage;
    }

    /**
     * Stands in for a storage whose construction would blow up - in production that is
     * an S3 client, here anything that fails the moment the locator resolves it.
     */
    private function createUnusableStorage(): callable
    {
        return static fn (): FilesystemOperator => throw new LogicException('This storage should not have been built.');
    }

    private function createPackages(): Packages
    {
        return new Packages(
            new PathPackage('/', new EmptyVersionStrategy()),
            ['s3' => new UrlPackage('https://data.by-night.fr', new EmptyVersionStrategy())],
        );
    }

    private function createResolver(
        FilesystemOperator|callable|null $events = null,
        FilesystemOperator|callable|null $users = null,
        FilesystemOperator|callable|null $pages = null,
    ): OriginUrlResolver {
        $factory = static fn (FilesystemOperator|callable|null $override, FilesystemOperator $default): callable => match (true) {
            null === $override => static fn (): FilesystemOperator => $default,
            $override instanceof FilesystemOperator => static fn (): FilesystemOperator => $override,
            default => $override,
        };

        return new OriginUrlResolver(
            $this->createPackages(),
            $this->cache,
            new NullLogger(),
            new ServiceLocator([
                'uploads/documents' => $factory($events, $this->events),
                'uploads/users' => $factory($users, $this->users),
                'uploads/pages' => $factory($pages, $this->pages),
            ]),
            $this->projectDir,
        );
    }
}
