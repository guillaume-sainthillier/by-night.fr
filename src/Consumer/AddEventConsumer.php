<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use Exception;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Psr\Log\LoggerInterface;

class AddEventConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    public function __construct(LoggerInterface $logger, private DoctrineEventHandler $doctrineEventHandler)
    {
        parent::__construct($logger);
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return 1|-1
     */
    public function batchExecute(array $messages): int
    {
        $dtos = [];
        foreach ($messages as $message) {
            $dtos[] = unserialize($message->getBody());
        }

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($dtos) {
                $this->doctrineEventHandler->handleManyCLI($dtos);
            });
            Monitor::displayStats();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => $dtos,
            ]);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }
}
