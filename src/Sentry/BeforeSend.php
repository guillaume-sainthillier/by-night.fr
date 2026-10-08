<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Sentry;

use App\Cdn\CdnPurgeQuotaExceededException;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Drops the purges Cloudflare refused for quota, which Messenger retries (config/packages/sentry.yaml).
 *
 * Sentry's Messenger listener captures one event per message of the refused batch. "ignore_exceptions" would not do:
 * it also matches the previous exceptions, so it would hide the worker's final "Removing from transport" log, whose
 * HandlerFailedException wraps the same exception. Only the events captured from this exception itself are dropped.
 */
final class BeforeSend
{
    public function __invoke(Event $event, ?EventHint $hint): ?Event
    {
        if ($hint?->exception instanceof CdnPurgeQuotaExceededException) {
            return null;
        }

        return $event;
    }
}
