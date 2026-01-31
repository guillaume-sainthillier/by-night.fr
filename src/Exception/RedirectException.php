<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Exception;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exception thrown when a redirect is needed (e.g., SEO redirects for canonical URLs).
 */
final class RedirectException extends Exception
{
    public function __construct(
        private readonly string $url,
        private readonly int $statusCode = Response::HTTP_MOVED_PERMANENTLY,
    ) {
        parent::__construct(\sprintf('Redirect to %s', $url));
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
