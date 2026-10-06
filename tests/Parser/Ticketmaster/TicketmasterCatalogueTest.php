<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser\Ticketmaster;

use App\Parser\Ticketmaster\TicketmasterCatalogue;
use App\Parser\Ticketmaster\TicketmasterPerformance;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The Discovery Feed as served on 2026-10-06: an index of one file per country and format,
 * then the French CSV, one row per performance.
 */
final class TicketmasterCatalogueTest extends TestCase
{
    private const string CSV_URI = 'https://s3.amazonaws.com/feeds/EVENTS_RAW-FR.csv.gz';

    private string $tempPath;

    private TestHandler $logs;

    #[Override]
    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir();
        $this->logs = new TestHandler();
    }

    public function testThePerformancesAreGroupedByShowInChronologicalOrder(): void
    {
        $requests = [];
        $shows = $this->catalogue(new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $url;

            return str_starts_with($url, self::CSV_URI) ? new MockResponse(self::csv([
                self::row(['EVENT_START_LOCAL_DATE' => '2026-11-18', 'EVENT_START_LOCAL_TIME' => '20:00', 'EVENT_STATUS' => 'offsale']),
                self::row(['EVENT_START_LOCAL_DATE' => '2026-11-17', 'EVENT_START_LOCAL_TIME' => '20:00']),
                self::row(['EVENT_START_LOCAL_DATE' => '2026-11-17', 'EVENT_START_LOCAL_TIME' => '15:00']),
                self::row(['PRIMARY_EVENT_URL' => 'https://www.ticketmaster.fr/fr/manifestation/autre-billet/idmanif/2/idtier/3', 'VENUE_LATITUDE' => '', 'VENUE_LONGITUDE' => '']),
            ])) : self::index();
        }))->shows();

        self::assertStringContainsString('apikey=consumer-key', $requests[0]);
        self::assertStringContainsString('countryCode=FR', $requests[0]);
        self::assertCount(2, $shows);
        self::assertArrayHasKey('2', $shows);

        $show = $shows['644289'];
        self::assertSame('https://s1.ticketm.net/dam/c/fbc/b293_TABLET_LANDSCAPE_LARGE_16_9.jpg', $show->imageUrl);
        self::assertSame(43.5517, $show->latitude);
        self::assertSame(1.4826, $show->longitude);
        self::assertSame(
            ['2026-11-17 15:00 onsale', '2026-11-17 20:00 onsale', '2026-11-18 20:00 offsale'],
            array_map(static fn (TicketmasterPerformance $performance): string => \sprintf(
                '%s %s %s',
                $performance->date->format('Y-m-d'),
                $performance->time?->format('H:i'),
                $performance->status,
            ), $show->performances),
        );
        self::assertSame('00:00', $show->performances[0]->date->format('H:i'));

        self::assertNull($shows['2']->latitude, 'A venue without coordinates');
        self::assertNull($shows['2']->longitude);
    }

    public function testPackagesAndUndatedRowsAreLeftOut(): void
    {
        $shows = $this->catalogue(new MockHttpClient([self::index(), new MockResponse(self::csv([
            self::row(['PRIMARY_EVENT_URL' => 'https://www.ticketmaster.fr/fr/pack/package-mask-singer-billet/idpack/4762']),
            self::row(['EVENT_START_LOCAL_DATE' => '']),
        ]))]))->shows();

        self::assertSame([], $shows);
    }

    public function testNoKeyNoCatalogue(): void
    {
        $client = new MockHttpClient([]);

        self::assertSame([], $this->catalogue($client, 'null')->shows());
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testAFeedThatCannotBeReadGivesAnEmptyCatalogueAndAnError(): void
    {
        self::assertSame([], $this->catalogue(new MockHttpClient([new MockResponse('', ['http_code' => 401])]))->shows(), 'A revoked key');
        self::assertSame([], $this->catalogue(new MockHttpClient([new JsonMockResponse(['countries' => []])]))->shows(), 'No French file');
        // gzopen() reads a plain file as is: an empty file is what cannot be read
        self::assertSame([], $this->catalogue(new MockHttpClient([self::index(), new MockResponse('')]))->shows(), 'An empty file');

        self::assertCount(3, $this->logs->getRecords());
        self::assertTrue($this->logs->hasErrorThatContains('Ticketmaster feed'));
    }

    private function catalogue(MockHttpClient $client, string $apiKey = 'consumer-key'): TicketmasterCatalogue
    {
        return new TicketmasterCatalogue($client, new Logger('test', [$this->logs]), $this->tempPath, $apiKey);
    }

    private static function index(): JsonMockResponse
    {
        return new JsonMockResponse(['countries' => ['FR' => [
            'JSON' => ['uri' => 'https://s3.amazonaws.com/feeds/EVENTS_RAW-FR.json.gz', 'num_events' => 3],
            'CSV' => ['uri' => self::CSV_URI, 'num_events' => 3],
        ]]]);
    }

    /**
     * @param list<array<string, string>> $rows
     */
    private static function csv(array $rows): string
    {
        $handle = fopen('php://memory', 'w+');
        self::assertNotFalse($handle);
        fputcsv($handle, array_keys(self::row()), escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        rewind($handle);

        return gzencode((string) stream_get_contents($handle));
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function row(array $overrides = []): array
    {
        return array_replace([
            'EVENT_ID' => 'ZkyMmBwZ1A7Ft4o',
            'EVENT_NAME' => 'SLIFT',
            'EVENT_STATUS' => 'onsale',
            'EVENT_START_LOCAL_DATE' => '2026-11-17',
            'EVENT_START_LOCAL_TIME' => '19:30',
            'EVENT_IMAGE_URL' => 'https://s1.ticketm.net/dam/c/fbc/b293_TABLET_LANDSCAPE_LARGE_16_9.jpg',
            'VENUE_NAME' => 'LE BIKINI',
            'VENUE_LATITUDE' => '43.5517',
            'VENUE_LONGITUDE' => '1.4826',
            'PRIMARY_EVENT_URL' => 'https://www.ticketmaster.fr/fr/manifestation/slift-billet/idmanif/644289/idtier/18864121',
        ], $overrides);
    }
}
