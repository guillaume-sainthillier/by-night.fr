<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Psr\Log\LoggerInterface;

class AddEventConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    private DoctrineEventHandler $doctrineEventHandler;

    public function __construct(LoggerInterface $logger, DoctrineEventHandler $doctrineEventHandler)
    {
        parent::__construct($logger);

        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function batchExecute(array $messages)
    {
        $dtos = [];
        foreach ($messages as $message) {
            $dtos[] = unserialize($message->getBody());
        }

        Monitor::bench('ADD EVENT BATCH', function () use ($dtos) {
            $this->doctrineEventHandler->handleManyCLI($dtos);
        });
        Monitor::displayStats();

        return ConsumerInterface::MSG_ACK;
    }
}
