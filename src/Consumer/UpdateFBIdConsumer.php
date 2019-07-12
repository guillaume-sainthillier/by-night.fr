<?php

namespace App\Consumer;

use App\Handler\DoctrineEventHandler;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class UpdateFBIdConsumer implements BatchConsumerInterface
{
    /**
     * @var DoctrineEventHandler
     */
    private $doctrineEventHandler;

    public function __construct(DoctrineEventHandler $doctrineEventHandler)
    {
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function batchExecute(array $messages)
    {
        $ids = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $data = \unserialize($message->body);
            $ids[$data['old']] = $ids[$data['new']];
        }

        $this->doctrineEventHandler->handleIdsToMigrate($ids);

        return ConsumerInterface::MSG_ACK;
    }
}
