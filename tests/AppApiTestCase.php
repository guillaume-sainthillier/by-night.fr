<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use Override;

/**
 * The API test client requests the site's own host over HTTPS (APP_URL), as AppWebTestCase does.
 */
abstract class AppApiTestCase extends ApiTestCase
{
    /**
     * @param array<string, mixed> $kernelOptions
     * @param array<string, mixed> $defaultOptions
     */
    #[Override]
    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        return parent::createClient($kernelOptions, $defaultOptions + ['base_uri' => (string) $_SERVER['APP_URL']]);
    }
}
