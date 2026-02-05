<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\PurgeCdnCacheUrl;
use Aws\CloudFront\CloudFrontClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

#[AsMessageHandler]
final class PurgeCdnCacheUrlHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private readonly CloudFrontClient $client,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'AWS_CLOUDFRONT_DISTRIBUTION_ID')]
        private readonly string $cloudFrontDistributionID,
    ) {
    }

    public function __invoke(PurgeCdnCacheUrl $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function process(array $jobs): void
    {
        $paths = array_map(static fn (array $job): string => $job[0]->path, $jobs);

        try {
            $this->client->createInvalidation([
                'DistributionId' => $this->cloudFrontDistributionID,
                'InvalidationBatch' => [
                    'CallerReference' => uniqid(),
                    'Paths' => [
                        'Items' => $paths,
                        'Quantity' => \count($paths),
                    ],
                ],
            ]);

            foreach ($jobs as [$message, $ack]) {
                $ack->ack();
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'paths' => $paths,
                ],
            ]);

            foreach ($jobs as [$message, $ack]) {
                $ack->nack($e);
            }
        }
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function getBatchSize(): int
    {
        return 30;
    }
}
