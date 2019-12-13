<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use App\Factory\EventFactory;
use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class AddEventConsumer extends AbstractConsumer implements ConsumerInterface, BatchConsumerInterface
{
    /**
     * @var EventFactory
     */
    private $eventFactory;

    /**
     * @var DoctrineEventHandler
     */
    private $doctrineEventHandler;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager, EventFactory $eventFactory, DoctrineEventHandler $doctrineEventHandler)
    {
        parent::__construct($logger);

        $this->entityManager = $entityManager;
        $this->eventFactory = $eventFactory;
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function execute(AMQPMessage $msg)
    {
        $datas = \json_decode($msg->getBody(), true);

        try {
            $event = $this->eventFactory->fromArray($datas);
            $this->doctrineEventHandler->handleOne($event);
        } catch (\Exception $e) {
            $this->logger->critical($e, $datas);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }

    public function batchExecute(array $messages)
    {
        $this->ping($this->entityManager->getConnection());

        /** @var AMQPMessage $message */
        $events = [];
        foreach ($messages as $message) {
            $event = \json_decode($message->body, true);
            $events[] = $this->eventFactory->fromArray($event);
        }

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($events) {
                $this->doctrineEventHandler->handleManyCLI($events);
            });
            Monitor::displayStats();
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }

    private function ping(Connection $connection)
    {
        if (false === $connection->ping()) {
            $connection->close();
            $connection->connect();
        }
    }
}
