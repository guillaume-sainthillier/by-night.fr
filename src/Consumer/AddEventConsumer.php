<?php

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
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class AddEventConsumer implements ConsumerInterface, BatchConsumerInterface
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

    /** @var LoggerInterface */
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger, EventFactory $eventFactory, DoctrineEventHandler $doctrineEventHandler)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->eventFactory = $eventFactory;
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function execute(AMQPMessage $msg)
    {
        $datas = \json_decode($msg->getBody(), true);
        $event = $this->eventFactory->fromArray($datas);

        try {
            $this->doctrineEventHandler->handleOne($event);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return ConsumerInterface::MSG_REJECT_REQUEUE;
        }

        return ConsumerInterface::MSG_ACK;
    }

    public function batchExecute(array $messages)
    {
        $this->ping($this->entityManager->getConnection());

        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERBOSE);
        Monitor::$output = $output;
        Monitor::enableMonitoring($output->isVerbose());

        $events = [];
        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $events[] = $this->eventFactory->fromArray(\json_decode($message->body, true));
        }

        try {
            Monitor::bench('ADD EVENT BATCH', function () use ($events) {
                $this->doctrineEventHandler->handleManyCLI($events);
            });
            Monitor::displayStats();
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return ConsumerInterface::MSG_REJECT_REQUEUE;
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
