<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Cdn;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Cloudflare refused a purge: the purge quota is empty (HTTP 429).
 *
 * The "cloudflare_purge" limiter follows the published quota, but Cloudflare counts the purges made by hand too, and
 * the overnight imports still met it about four times a day. The message is retried once Cloudflare says the quota
 * has refilled, within the max_retries of the transport. Sentry drops these soft failures (App\Sentry\BeforeSend):
 * only a purge still refused after its last retry reaches it.
 */
final class CdnPurgeQuotaExceededException extends RecoverableMessageHandlingException
{
    /** Retry delay when Cloudflare sends no Retry-After header: the limiter refills a token every 12 s. */
    public const int DEFAULT_RETRY_DELAY_MS = 60_000;

    public function __construct(?string $retryAfter)
    {
        $retryDelay = is_numeric($retryAfter) ? (int) $retryAfter * 1000 : self::DEFAULT_RETRY_DELAY_MS;

        parent::__construct('Cloudflare purge quota exceeded', 429, retryDelay: $retryDelay, forceRetry: false);
    }
}
