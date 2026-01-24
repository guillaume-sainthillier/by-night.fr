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
    if ($context['APP_MAINTENANCE']) {
        return new Response(file_get_contents(__DIR__ . '/maintenance.html'), Response::HTTP_SERVICE_UNAVAILABLE);
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
