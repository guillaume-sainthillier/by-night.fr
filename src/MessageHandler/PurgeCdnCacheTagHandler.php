<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Message\PurgeCdnCacheTag;
use App\Message\PurgeCdnCacheTags;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * The one-tag messages queued before PurgeCdnCacheTags, still on the "async" queue of the release that sent them:
 * forwarded 100 at a time to "cdn" instead of holding the "async" worker in the Cloudflare throttle. To delete, with
 * PurgeCdnCacheTag, once production has drained them.
 */
#[AsMessageHandler]
final class PurgeCdnCacheTagHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function __invoke(PurgeCdnCacheTag $message, ?Acknowledger $ack = null): mixed
    {
        return $this->handle($message, $ack);
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function process(array $jobs): void
    {
        $tags = array_values(array_unique(array_map(static fn (array $job): string => $job[0]->tag, $jobs)));

        try {
            $this->messageBus->dispatch(new PurgeCdnCacheTags($tags));

            foreach ($jobs as [$message, $ack]) {
                $ack->ack();
            }
        } catch (Throwable $e) {
            foreach ($jobs as [$message, $ack]) {
                $ack->nack($e);
            }
        }
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function getBatchSize(): int
    {
        return 100;
    }
}
