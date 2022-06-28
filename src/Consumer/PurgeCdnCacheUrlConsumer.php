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
    public function __construct(LoggerInterface $logger, private CloudFrontClient $client, private string $cloudFrontDistributionID)
    {
        parent::__construct($logger);
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return -1|1
     */
    public function batchExecute(array $messages): int
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
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'extra' => [
                    'paths' => $paths,
                ],
            ]);

            return ConsumerInterface::MSG_REJECT;
        }
    }
}
