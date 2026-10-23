<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Analytics;

use App\Analytics\EventVisitBackfill;
use App\Analytics\GoogleAnalyticsClient;
use App\Factory\EventFactory;
use App\Factory\EventVisitStatFactory;
use App\Repository\EventVisitStatRepository;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EventVisitBackfillTest extends AppKernelTestCase
{
    private const string PROPERTY_ID = '316503959';

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    private string $publicKey = '';

    public function testFillsADayBeforeUmamiFlaggedAsGoogleAnalytics(): void
    {
        $event = EventFactory::createOne();
        $id = $event->getId();
        EventVisitStatFactory::createOne(['day' => new DateTimeImmutable('2026-10-23'), 'visitors' => 3]);

        $read = $this->backfill()->import($this->client([
            ['/toulouse/soiree/concert--' . $id, 9],
            ['/toulouse/soiree/grand-concert--' . $id, 2],
            ['(other)', 40],
            ['/toulouse/soiree/gone--999999999', 5],
        ]), new DateTimeImmutable('2026-10-20'));

        self::assertSame(['events' => 1, 'visitors' => 16, 'unattributed' => 40], $read);
        $stat = EventVisitStatFactory::find(['event' => $event]);
        self::assertSame(11, $stat->getVisitors());
        self::assertSame(0, $stat->getTicketClicks(), 'GA4 cannot tell a ticketing link among the outbound clicks');
        self::assertTrue($stat->isFromGoogleAnalytics());
        self::assertEquals(new DateTimeImmutable('2026-10-20'), $this->repository()->findLastGoogleAnalyticsDay((int) $id));
        self::assertEquals(new DateTimeImmutable('2026-10-23'), $this->repository()->findFirstUmamiDay());
    }

    public function testNeverOverwritesADayReadFromUmami(): void
    {
        EventVisitStatFactory::createOne(['day' => new DateTimeImmutable('2026-10-20'), 'visitors' => 3]);

        $this->expectException(LogicException::class);

        $this->backfill()->import($this->client([]), new DateTimeImmutable('2026-10-21'));
    }

    public function testADryRunWritesNothing(): void
    {
        $event = EventFactory::createOne();

        $read = $this->backfill()->import($this->client([['/toulouse/soiree/concert--' . $event->getId(), 9]]), new DateTimeImmutable('2026-10-20'), dryRun: true);

        self::assertSame(['events' => 1, 'visitors' => 9, 'unattributed' => 0], $read);
        self::assertSame(0, EventVisitStatFactory::count());
    }

    public function testAsksGoogleForTheEventPagesOfTheDayWithASignedToken(): void
    {
        $this->backfill()->import($this->client([]), new DateTimeImmutable('2026-10-20'));

        self::assertSame('https://oauth2.googleapis.com/token', $this->requests[0]['url']);
        parse_str((string) $this->requests[0]['options']['body'], $form);
        self::assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $form['grant_type']);
        [$header, $claims, $signature] = explode('.', (string) $form['assertion']);
        $decode = static fn (string $part): string => (string) base64_decode(strtr($part, '-_', '+/'), true);
        self::assertSame(1, openssl_verify("$header.$claims", $decode($signature), $this->publicKey, \OPENSSL_ALGO_SHA256));
        $claims = json_decode($decode($claims), true);
        self::assertSame('ga4@by-night.iam.gserviceaccount.com', $claims['iss']);
        self::assertSame('https://www.googleapis.com/auth/analytics.readonly', $claims['scope']);

        self::assertSame('https://analyticsdata.googleapis.com/v1beta/properties/' . self::PROPERTY_ID . ':runReport', $this->requests[1]['url']);
        $report = json_decode((string) $this->requests[1]['options']['body'], true);
        self::assertSame([['startDate' => '2026-10-20', 'endDate' => '2026-10-20']], $report['dateRanges']);
        self::assertSame('/soiree/', $report['dimensionFilter']['filter']['stringFilter']['value']);
        self::assertContains('Authorization: Bearer access-token', $this->requests[1]['options']['headers']);
    }

    /**
     * @param list<array{string, int}> $rows path, users
     */
    private function client(array $rows): GoogleAnalyticsClient
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $this->publicKey = (string) openssl_pkey_get_details($key)['key'];

        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($rows): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return match (true) {
                str_ends_with($url, '/token') => new JsonMockResponse(['access_token' => 'access-token', 'expires_in' => 3600]),
                str_ends_with($url, ':runReport') => new JsonMockResponse([
                    'rows' => array_map(static fn (array $row): array => ['dimensionValues' => [['value' => $row[0]]], 'metricValues' => [['value' => (string) $row[1]]]], $rows),
                    'rowCount' => \count($rows),
                ]),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });

        return new GoogleAnalyticsClient($http, ['client_email' => 'ga4@by-night.iam.gserviceaccount.com', 'private_key' => (string) $privateKey], self::PROPERTY_ID);
    }

    private function backfill(): EventVisitBackfill
    {
        return new EventVisitBackfill($this->repository());
    }

    private function repository(): EventVisitStatRepository
    {
        return self::getContainer()->get(EventVisitStatRepository::class);
    }
}
