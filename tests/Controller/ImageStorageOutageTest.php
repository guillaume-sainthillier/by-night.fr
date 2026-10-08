<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Silarhi\PicassoBundle\Service\ImageHelperInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

/**
 * The uploads bucket failing during a thumbnail miss (OVH S3 outage, 2026-10-07: BY-NIGHTFR-6B0, 175 uncaught 500s
 * logged as critical): Picasso answers 503 with a Retry-After that no cache keeps, logged as a warning, which Sentry
 * does not receive.
 */
final class ImageStorageOutageTest extends AppWebTestCase
{
    public function testAThumbnailWhoseSourceCannotBeReadIsAnUncacheable503(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        self::getContainer()->set('events.storage', $this->eventsStorageFailingToRead());
        $url = self::getContainer()->get(ImageHelperInterface::class)->imageUrl('2026/10/05/affiche.jpg', width: 360, format: 'webp', loader: 'event_image');

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertResponseHeaderSame('Retry-After', '30');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'), 'Cloudflare must not keep it the minute it keeps a 404.');
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('logger');
        self::assertInstanceOf(LoggerDataCollector::class, $collector);
        self::assertSame(0, $collector->countErrors());
    }

    /**
     * The events storage on a real S3 client, as in production: the image exists (HeadObject), but reading it gets no
     * answer, as when the bucket stopped answering during the outage.
     */
    private function eventsStorageFailingToRead(): Filesystem
    {
        $responses = new MockHandler();
        $responses->append(new Result([]));
        $responses->append(static fn (CommandInterface $command): S3Exception => new S3Exception('Error executing "GetObject": Failed to open stream: HTTP request failed!', $command));

        $s3 = new S3Client([
            'version' => '2006-03-01',
            'region' => 'gra',
            'endpoint' => 'https://s3.test',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'key', 'secret' => 'secret'],
            'retries' => 0,
            'handler' => $responses,
        ]);

        return new Filesystem(new AwsS3V3Adapter($s3, 'by-night-data', 'uploads/documents'));
    }
}
