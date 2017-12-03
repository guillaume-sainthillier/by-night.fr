<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 06/09/2017
 * Time: 19:32.
 */

namespace AppBundle\Consumer;

use AppBundle\Handler\DoctrineEventHandler;
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
        dump('OK');
        $ids = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $message) {
            $data              = \unserialize($message->body);
            $ids[$data['old']] = $ids[$data['new']];
        }

        dump($ids);
        $this->doctrineEventHandler->handleIdsToMigrate($ids);

        return ConsumerInterface::MSG_ACK;
    }
}
