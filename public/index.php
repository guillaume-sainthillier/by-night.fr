<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    if ($trustedProxies = $context['TRUSTED_PROXIES'] ?? false) {
        Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_TRAEFIK);
    }

    if ($trustedHosts = $context['TRUSTED_HOSTS'] ?? false) {
        Request::setTrustedHosts([$trustedHosts]);
    }

    if ($context['APP_MAINTENANCE']) {
        require_once __DIR__ . '/maintenance.html';

        return null;
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
