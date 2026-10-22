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

    /**
     * A page that is not a number reads as page 0, which API Platform's Pagination refuses: a 400, which
     * Sentry got as "fatal" (BY-NIGHTFR-6D0), its exception being no HttpException.
     */
    public function testAPageThatIsNotANumberIsLoggedAsTheOther400s(): void
    {
        self::assertSame(['ERROR'], $this->loggedErrorLevels('/api/search?q=concert&page=abc', 400));
    }

    public function testANotFoundPageIsStillLoggedAsAnError(): void
    {
        self::assertSame(['ERROR'], $this->loggedErrorLevels('/wp-admin/setup-config.php', 404));
    }

    /**
     * @return list<string> the levels of the records of the "error" level and above
     */
    private function loggedErrorLevels(string $uri, int $statusCode): array
    {
        return array_map(
            static fn (array $log): string => $log['priorityName'],
            array_values(array_filter($this->getLogs($uri, $statusCode), static fn (array $log): bool => $log['priority'] >= 400)),
        );
    }

    private function countLoggedErrors(string $uri, int $statusCode): int
    {
        return \count($this->loggedErrorLevels($uri, $statusCode));
    }

    /**
     * @return list<array{priority: int, priorityName: string}>
     */
    private function getLogs(string $uri, int $statusCode): array
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', $uri);

        self::assertResponseStatusCodeSame($statusCode);
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        $collector = $profile->getCollector('logger');
        self::assertInstanceOf(LoggerDataCollector::class, $collector);

        return $collector->getProcessedLogs();
    }
}
