<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use Aws\CloudFront\CloudFrontClient;
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

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

    /**
     * {@inheritDoc}
     */
    public function batchExecute(array $messages)
    {
        $paths = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $path = $message->getBody();
            $paths[] = $path;
        }

        try {
            $this->client->createInvalidation([
                'DistributionId' => $this->cloudFrontDistributionID,
                'InvalidationBatch' => [
                    'CallerReference' => uniqid(),
                    'Paths' => [
                        'Items' => $paths,
                        'Quantity' => 1,
                    ],
                ],
            ]);

            return ConsumerInterface::MSG_ACK;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => [
                    'paths' => $paths,
                ],
            ]);

            return ConsumerInterface::MSG_REJECT;
        }
    }
}
