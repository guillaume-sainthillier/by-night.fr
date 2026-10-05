<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Cdn\CloudflareCdnPurger;
use App\Message\PurgeCdnCacheTags;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Throwable;

/**
 * Packs the tags of consecutive messages into Cloudflare requests of at most MAX_FILES_PER_REQUEST distinct tags.
 * Every request costs a token of the "cloudflare_purge" bucket whatever it carries, and a tag queued again by a
 * later flush (an event re-imported, a venue shared by the batch's events) costs nothing in the same request.
 */
#[AsMessageHandler]
final class PurgeCdnCacheTagsHandler implements BatchHandlerInterface
{
    use BatchHandlerTrait;

    public function __construct(
        private readonly CloudflareCdnPurger $cdnPurger,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeCdnCacheTags $message, ?Acknowledger $ack = null): mixed
    {
        // A message is acked as a whole, so its tags never straddle two requests: send the pending ones first
        if (null !== $ack && \count(self::distinctTags([...$this->jobs, [$message, $ack]])) > CloudflareCdnPurger::MAX_FILES_PER_REQUEST) {
            $this->flush(true);
        }

        return $this->handle($message, $ack);
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function process(array $jobs): void
    {
        $tags = self::distinctTags($jobs);

        try {
            $this->cdnPurger->purgeTags($tags);

            foreach ($jobs as [$message, $ack]) {
                $ack->ack();
            }
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'tags' => $tags,
                ],
            ]);

            foreach ($jobs as [$message, $ack]) {
                $ack->nack($e);
            }
        }
    }

    /**
     * @param array<array{0: object, 1: Acknowledger}> $jobs
     *
     * @return list<string>
     */
    private static function distinctTags(array $jobs): array
    {
        $tags = [];
        foreach ($jobs as [$message]) {
            if ($message instanceof PurgeCdnCacheTags) {
                foreach ($message->tags as $tag) {
                    $tags[$tag] = true;
                }
            }
        }

        return array_map(strval(...), array_keys($tags));
    }

    /** @phpstan-ignore method.unused (called by BatchHandlerTrait) */
    private function getBatchSize(): int
    {
        // A message carries at least one tag: 100 of them always fill a request
        return CloudflareCdnPurger::MAX_FILES_PER_REQUEST;
    }
}
