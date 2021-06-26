<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use App\Factory\EventFactory;
use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use Exception;
use const JSON_THROW_ON_ERROR;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class AddEventConsumer extends AbstractConsumer implements ConsumerInterface, BatchConsumerInterface
{
    private EventFactory $eventFactory;

    private DoctrineEventHandler $doctrineEventHandler;

    public function __construct(LoggerInterface $logger, EventFactory $eventFactory, DoctrineEventHandler $doctrineEventHandler)
    {
        parent::__construct($logger);

        $this->entityManager = $entityManager;
        $this->eventFactory = $eventFactory;
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function execute(AMQPMessage $msg)
    {
        $datas = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);

        try {
            $event = $this->eventFactory->fromArray($datas);
            $this->doctrineEventHandler->handleOne($event);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'extra' => $datas,
            ]);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }

    public function batchExecute(array $messages)
    {
        /** @var AMQPMessage $message */
        $events = [];
        foreach ($messages as $message) {
            $datas = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            try {
                $events[] = $this->eventFactory->fromArray($datas);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), [
                    'extra' => $datas,
                    'exception' => $e,
                ]);
            }
        }

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($events) {
                $this->doctrineEventHandler->handleManyCLI($events);
            });
            Monitor::displayStats();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
            ]);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }
}
