<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Ticketmaster;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The shows on sale at Ticketmaster France, read from the Discovery Feed: one CSV of every
 * performance in the country, rebuilt daily (69,720 performances of 18,933 shows on 2026-10-06).
 * The feed index answers with a file per country and format; only the French CSV is read.
 *
 * The API key is the "consumer key" of a developer.ticketmaster.com app (the secret is not used).
 */
final readonly class TicketmasterCatalogue
{
    private const string FEED_INDEX_URL = 'https://app.ticketmaster.com/discovery-feed/v2/events';

    private const string COUNTRY = 'FR';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/var/storage/temp')]
        private string $tempPath,
        #[Autowire(env: 'TICKETMASTER_API_KEY')]
        private string $apiKey,
    ) {
    }

    /**
     * The shows by their id (numeric: PHP makes the keys ints, a lookup by the string id works
     * all the same). Empty without an API key, or when the feed cannot be read: the catalogue
     * only completes another source, which is imported without it rather than not at all.
     *
     * @return array<array-key, TicketmasterShow>
     */
    public function shows(): array
    {
        if ('' === $this->apiKey || 'null' === $this->apiKey) {
            return [];
        }

        try {
            $path = $this->download();
        } catch (ExceptionInterface|RuntimeException $exception) {
            $this->logger->error('The Ticketmaster feed could not be downloaded', ['exception' => $exception]);

            return [];
        }

        try {
            return $this->read($path);
        } catch (RuntimeException $exception) {
            $this->logger->error('The Ticketmaster feed could not be read', ['exception' => $exception]);

            return [];
        } finally {
            new Filesystem()->remove($path);
        }
    }

    private function download(): string
    {
        $index = $this->httpClient->request('GET', self::FEED_INDEX_URL, ['query' => [
            'apikey' => $this->apiKey,
            'countryCode' => self::COUNTRY,
        ]])->toArray();

        $uri = $index['countries'][self::COUNTRY]['CSV']['uri'] ?? null;
        if (!\is_string($uri)) {
            throw new RuntimeException(\sprintf('The Ticketmaster feed index lists no CSV file for %s', self::COUNTRY));
        }

        $response = $this->httpClient->request('GET', $uri);
        $path = \sprintf('%s/ticketmaster-%s.csv.gz', $this->tempPath, mb_strtolower(self::COUNTRY));
        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Unable to write %s', $path));
        }

        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * @return array<array-key, TicketmasterShow>
     */
    private function read(string $path): array
    {
        $handle = gzopen($path, 'r');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Unable to open gzipped file: %s', $path));
        }

        /** @var array<array-key, array{image: ?string, latitude: ?float, longitude: ?float, performances: list<TicketmasterPerformance>}> $shows */
        $shows = [];
        try {
            $headers = fgetcsv($handle, escape: '');
            if (false === $headers) {
                throw new RuntimeException('Unable to read the CSV headers of the Ticketmaster feed');
            }

            while (false !== ($row = fgetcsv($handle, escape: ''))) {
                if (\count($row) !== \count($headers)) {
                    continue;
                }

                $row = array_combine($headers, $row);
                // Packages (".../pack/.../idpack/4762") are no show
                if (!preg_match('#/idmanif/(\d+)#', $row['PRIMARY_EVENT_URL'] ?? '', $matches)) {
                    continue;
                }

                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $row['EVENT_START_LOCAL_DATE'] ?? '');
                if (false === $date) {
                    continue;
                }

                // Every performance of a show carries the same picture and venue
                $id = $matches[1];
                $shows[$id] ??= [
                    'image' => '' !== ($row['EVENT_IMAGE_URL'] ?? '') ? $row['EVENT_IMAGE_URL'] : null,
                    'latitude' => is_numeric($row['VENUE_LATITUDE'] ?? null) ? (float) $row['VENUE_LATITUDE'] : null,
                    'longitude' => is_numeric($row['VENUE_LONGITUDE'] ?? null) ? (float) $row['VENUE_LONGITUDE'] : null,
                    'performances' => [],
                ];
                $shows[$id]['performances'][] = new TicketmasterPerformance(
                    $date,
                    DateTimeImmutable::createFromFormat('!H:i', $row['EVENT_START_LOCAL_TIME'] ?? '') ?: null,
                    $row['EVENT_STATUS'] ?? '',
                );
            }
        } finally {
            gzclose($handle);
        }

        return array_map(static function (array $show): TicketmasterShow {
            $performances = $show['performances'];
            usort($performances, static fn (TicketmasterPerformance $a, TicketmasterPerformance $b): int => [$a->date, $a->time] <=> [$b->date, $b->time]);

            return new TicketmasterShow($show['image'], $show['latitude'], $show['longitude'], $performances);
        }, $shows);
    }
}
