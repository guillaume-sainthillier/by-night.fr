<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Updater;

use DateTimeInterface;
use App\Social\FacebookAdmin;
use Doctrine\ORM\EntityManagerInterface;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class Updater
{
    /** @var HttpClientInterface */
    protected $client;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var FacebookAdmin
     */
    protected $facebookAdmin;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, FacebookAdmin $facebookAdmin)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->facebookAdmin = $facebookAdmin;

        $this->client = HttpClient::create();
    }

    abstract public function update(DateTimeInterface $from);

    protected function downloadUrls(array $urls)
    {
        $requests = function ($urls) {
            foreach ($urls as $i => $url) {
                yield $i => new Request('GET', $url);
            }
        };

        $responses = [];
        $pool = new Pool($this->client, $requests($urls), [
            'concurrency' => self::POOL_SIZE,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$responses) {
                $responses[$index] = [
                    'contentType' => current($response->getHeader('Content-Type')),
                    'content' => copy_to_string($response->getBody()),
                ];
            },
            'rejected' => function (RequestException $reason, $index) use (&$responses) {
                $responses[$index] = null;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }
}
