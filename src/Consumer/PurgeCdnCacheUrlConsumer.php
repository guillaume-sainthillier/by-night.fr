<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use Aws\CloudFront\CloudFrontClient;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class PurgeCdnCacheUrlConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    private CloudFrontClient $client;

    private string $cloudFrontDistributionID;

    public function __construct(LoggerInterface $logger, CloudFrontClient $client, string $cloudFrontDistributionID)
    {
        parent::__construct($logger);

        $this->client = $client;
        $this->cloudFrontDistributionID = $cloudFrontDistributionID;
    }

    public function batchExecute(array $messages)
    {
        $paths = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $message) {
            $path = $message->getBody();
            $paths[] = $path;
        }

        try {
            $result = $this->client->createInvalidation([
                'DistributionId' => $this->cloudFrontDistributionID,
                'InvalidationBatch' => [
                    'CallerReference' => uniqid(),
                    'Paths' => [
                        'Items' => $paths,
                        'Quantity' => 1,
                    ],
                ]
            ]);

            dd($result);
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
