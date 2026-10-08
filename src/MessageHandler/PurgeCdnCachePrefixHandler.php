<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Cdn\CdnPurgeQuotaExceededException;
use App\Cdn\CloudflareCdnPurger;
use App\Message\PurgeCdnCachePrefix;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

#[AsMessageHandler]
final class PurgeCdnCachePrefixHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private readonly CloudflareCdnPurger $cdnPurger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeCdnCachePrefix $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function process(array $jobs): void
    {
        $prefixes = array_map(static fn (array $job): string => $job[0]->prefix, $jobs);

        try {
            $this->cdnPurger->purgePrefixes($prefixes);

            foreach ($jobs as [$message, $ack]) {
                $ack->ack();
            }
        } catch (Throwable $e) {
            // A refusal for quota is retried once Cloudflare's quota has refilled
            $this->logger->log($e instanceof CdnPurgeQuotaExceededException ? LogLevel::WARNING : LogLevel::ERROR, $e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'prefixes' => $prefixes,
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
        // One batch is one Cloudflare request, so fill it up to the "max operations per request".
        return CloudflareCdnPurger::MAX_FILES_PER_REQUEST;
    }
}
