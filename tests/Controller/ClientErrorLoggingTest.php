<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

/**
 * Sentry receives every record of the "error" level (framework.exceptions in config/packages/framework.yaml).
 */
final class ClientErrorLoggingTest extends AppWebTestCase
{
    public function testARefusedApiInputIsNotLoggedAsAnError(): void
    {
        self::assertSame(0, $this->countLoggedErrors('/api/tags', 422));
    }

    public function testANotFoundPageIsStillLoggedAsAnError(): void
    {
        self::assertSame(1, $this->countLoggedErrors('/wp-admin/setup-config.php', 404));
    }

    private function countLoggedErrors(string $uri, int $statusCode): int
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', $uri);

        self::assertResponseStatusCodeSame($statusCode);
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('logger');
        self::assertInstanceOf(LoggerDataCollector::class, $collector);

        return $collector->countErrors();
    }
}
