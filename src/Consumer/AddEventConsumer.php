<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 06/09/2017
 * Time: 19:32.
 */

namespace App\Consumer;

use App\Factory\EventFactory;
use App\Handler\DoctrineEventHandler;
use App\Utils\Monitor;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Output\ConsoleOutput;

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

    public function __construct(EventFactory $eventFactory, DoctrineEventHandler $doctrineEventHandler)
    {
        $this->eventFactory = $eventFactory;
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function execute(AMQPMessage $msg)
    {
        $datas = \json_decode($msg->getBody(), true);
        $event = $this->eventFactory->fromArray($datas);

        $this->doctrineEventHandler->handleOne($event);

        /*
        dump(
            $event->getId(),
            $event->getExternalId(),
            $event->getNom(),
            $event->getPlace()->getId(),
            $event->getPlace()->getNom(),
            $event->getPlace()->getReject()->isValid(),
            $event->getReject()->isValid()
        );
        die;
        */

        return ConsumerInterface::MSG_ACK;
    }

    public function batchExecute(array $messages)
    {
        Monitor::$output = new ConsoleOutput();
        $events = [];
        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $events[] = $this->eventFactory->fromArray(\json_decode($message->body, true));
        }

        $this->doctrineEventHandler->handleManyCLI($events);

        return ConsumerInterface::MSG_ACK;
    }
}
