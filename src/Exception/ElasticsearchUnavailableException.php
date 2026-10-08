<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Exception;

use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Elasticsearch is restarting (ElasticsearchUnavailableSubscriber): the page answers 503 with a Retry-After, which
 * crawlers honour and which lets Cloudflare serve its stale copy (stale-if-error). A warning, not an error: the outage
 * lasts a minute or two and every page that searches fails at once, which used to flood Sentry.
 */
#[WithLogLevel(LogLevel::WARNING)]
final class ElasticsearchUnavailableException extends ServiceUnavailableHttpException
{
    public function __construct(int $retryAfter, Throwable $previous)
    {
        parent::__construct($retryAfter, 'Elasticsearch is unavailable: ' . $previous->getMessage(), $previous);
    }
}
