<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 06/09/2017
 * Time: 19:32
 */

namespace AppBundle\Consumer;

use AppBundle\Factory\EventFactory;
use AppBundle\Handler\DoctrineEventHandler;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use AppBundle\Utils\Monitor;
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

    public function execute(AMQPMessage $msg) {
        $datas = unserialize($msg->body);
        $event = $this->eventFactory->fromArray($datas);
        dump($event);

        return ConsumerInterface::MSG_ACK;
    }

    public function batchExecute(array $messages) {
        Monitor::$output = new ConsoleOutput();
        $events = [];
        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $events[] = $this->eventFactory->fromArray(unserialize($message->body));
        }

        $this->doctrineEventHandler->handleManyCLI($events);

        return ConsumerInterface::MSG_ACK;
    }
}
