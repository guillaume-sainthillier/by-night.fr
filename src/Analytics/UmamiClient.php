<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Analytics;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads the site's audience from its self-hosted Umami (3.x) as a user allowed to view the website: a token from
 * auth/login, then the website's routes. Umami's event-data values stop at the top 100, so the counts per page come
 * from the routes that page: "metrics" for the visitors of every path, "events" for the custom events one by one.
 */
final class UmamiClient implements ResetInterface
{
    private const int METRICS_PAGE_SIZE = 5000;

    private const int EVENTS_PAGE_SIZE = 500;

    private ?string $token = null;

    public function __construct(
        #[Target('umami.client')]
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'UMAMI_WEBSITE_ID')]
        private readonly string $websiteId,
        #[Autowire(env: 'UMAMI_USERNAME')]
        private readonly string $username,
        #[Autowire(env: 'UMAMI_PASSWORD')]
        private readonly string $password,
    ) {
    }

    /**
     * The visitors of each page whose path contains $pathContains between $from and $to: a visitor of a page counts
     * once in the period, however many times they saw it.
     *
     * @return iterable<string, int> by path
     */
    public function pathVisitors(DateTimeImmutable $from, DateTimeImmutable $to, string $pathContains): iterable
    {
        for ($offset = 0;; $offset += self::METRICS_PAGE_SIZE) {
            $rows = $this->get('metrics', [
                ...$this->period($from, $to),
                'type' => 'path',
                'path' => 'c.' . $pathContains,
                'limit' => self::METRICS_PAGE_SIZE,
                'offset' => $offset,
            ]);

            foreach ($rows as $row) {
                yield (string) $row['x'] => (int) $row['y'];
            }

            if (\count($rows) < self::METRICS_PAGE_SIZE) {
                return;
            }
        }
    }

    /**
     * The path of the page of each custom event named $event between $from and $to.
     *
     * @return iterable<string>
     */
    public function eventPaths(string $event, DateTimeImmutable $from, DateTimeImmutable $to): iterable
    {
        for ($page = 1;; ++$page) {
            $result = $this->get('events', [
                ...$this->period($from, $to),
                'event' => $event,
                'page' => $page,
                'pageSize' => self::EVENTS_PAGE_SIZE,
            ]);

            foreach ($result['data'] ?? [] as $row) {
                if ($event === ($row['eventName'] ?? null)) {
                    yield (string) $row['urlPath'];
                }
            }

            if ($page * self::EVENTS_PAGE_SIZE >= (int) ($result['count'] ?? 0)) {
                return;
            }
        }
    }

    public function reset(): void
    {
        $this->token = null;
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<mixed>
     */
    private function get(string $route, array $query): array
    {
        return $this->httpClient->request('GET', \sprintf('api/websites/%s/%s', $this->websiteId, $route), [
            'auth_bearer' => $this->token(),
            'query' => $query,
        ])->toArray();
    }

    private function token(): string
    {
        return $this->token ??= (string) $this->httpClient->request('POST', 'api/auth/login', ['json' => [
            'username' => $this->username,
            'password' => $this->password,
        ]])->toArray()['token'];
    }

    /**
     * @return array{startAt: int, endAt: int}
     */
    private function period(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [
            'startAt' => (int) $from->format('Uv'),
            'endAt' => (int) $to->format('Uv'),
        ];
    }
}
