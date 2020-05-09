<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PurgeCdnCacheUrlConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    private Packages $packages;

    private HttpClientInterface $client;

    private string $cfZone;

    public function __construct(LoggerInterface $logger, Packages $packages, string $cfUserEmail, string $cfUserKey, string $cfZone)
    {
        parent::__construct($logger);

        $this->packages = $packages;
        $this->cfZone = $cfZone;

        $this->client = HttpClient::create([
            'base_uri' => 'https://api.cloudflare.com',
            'headers' => [
                'X-Auth-Email' => $cfUserEmail,
                'X-Auth-Key' => $cfUserKey,
            ],
        ]);
    }

    public function batchExecute(array $messages)
    {
        $urls = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $message) {
            $path = $message->getBody();
            $urls[] = $this->packages->getUrl($path, 'aws');
        }

        try {
            $response = $this->client->request(
                'POST',
                sprintf('/client/v4/zones/%s/purge_cache', $this->cfZone),
                ['json' => ['files' => $urls]]
            );

            $datas = $response->toArray();
            $success = $datas['success'];
            if (true === $success) {
                return ConsumerInterface::MSG_ACK;
            }

            $this->logger->error('CDN PURGE ERROR', [
                'extra' => $datas,
            ]);
        } catch (TransportExceptionInterface | HttpExceptionInterface $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'urls' => $urls,
                ],
            ]);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }
}
