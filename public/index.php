<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use App\Kernel;
use Symfony\Component\HttpFoundation\Response;

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

return static function (array $context) {
    // The prod image's .env.local.php returns [], so this is only set when the container environment sets it.
    if (filter_var($context['APP_MAINTENANCE'] ?? false, \FILTER_VALIDATE_BOOL)) {
        return new Response(file_get_contents(__DIR__ . '/maintenance.html'), Response::HTTP_SERVICE_UNAVAILABLE, [
            // Upper bound of a maintenance window, so crawlers come back right after it.
            // Symfony already defaults to Cache-Control: no-cache, private, which keeps this page out of shared caches.
            'Retry-After' => '60',
        ]);
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
