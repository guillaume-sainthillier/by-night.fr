<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests;

use LogicException;
use Override;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The test client browses the site's own host over HTTPS (APP_URL: https://by-night.test in .env.test) instead of
 * https://by-night.test: the URLs the tests read are the ones the router writes, and the host must pass
 * framework.trusted_hosts as in production.
 */
abstract class AppWebTestCase extends WebTestCase
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    #[Override]
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        return parent::createClient($options, $server + self::siteServerParameters());
    }

    /**
     * @return array{HTTP_HOST: string, HTTPS: string}
     */
    private static function siteServerParameters(): array
    {
        $url = parse_url((string) $_SERVER['APP_URL']);
        if (!\is_array($url) || !isset($url['host'])) {
            throw new LogicException(\sprintf('APP_URL "%s" names no host.', $_SERVER['APP_URL']));
        }

        return [
            'HTTP_HOST' => $url['host'],
            'HTTPS' => 'https' === ($url['scheme'] ?? null) ? 'on' : 'off',
        ];
    }
}
