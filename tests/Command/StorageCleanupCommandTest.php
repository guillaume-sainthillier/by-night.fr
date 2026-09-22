<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Command\StorageCleanupCommand;
use App\Tests\AppKernelTestCase;
use Aws\Api\DateTimeResult;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class StorageCleanupCommandTest extends AppKernelTestCase
{
    public function testAFileUploadedMomentsAgoIsNotDeleted(): void
    {
        $deleted = [];
        $handler = new MockHandler();
        $handler->append(new Result([
            'IsTruncated' => false,
            'Contents' => [
                // Its row may still be in an open transaction: no name references it yet
                ['Key' => 'uploads/documents/2026/09/22/just-uploaded.jpg', 'Size' => 10, 'LastModified' => new DateTimeResult('-5 minutes')],
                ['Key' => 'uploads/documents/2019/03/15/orphan.jpg', 'Size' => 20, 'LastModified' => new DateTimeResult('2019-03-15')],
            ],
        ]));
        $handler->append(static function (CommandInterface $command) use (&$deleted): Result {
            $deleted[] = $command['Key'];

            return new Result([]);
        });

        $client = new S3Client(['region' => 'eu-west-3', 'version' => 'latest', 'credentials' => false, 'handler' => $handler]);
        $bus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };
        $command = new StorageCleanupCommand(self::getContainer()->get(Connection::class), $client, 'bucket', $bus);

        new CommandTester($command)->execute([]);

        self::assertSame(['uploads/documents/2019/03/15/orphan.jpg'], $deleted);
    }
}
