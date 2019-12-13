<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Consumer;

use League\Glide\Server;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RemoveImageThumbnailsConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    /** @var Server */
    private $glide;

    public function __construct(LoggerInterface $logger, Server $glide)
    {
        parent::__construct($logger);

        $this->glide = $glide;
    }

    public function batchExecute(array $messages)
    {
        $result = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $message) {
            $path = $message->getBody();

            try {
                $this->deleteThumbnails($path);
                $result[(int) $message->delivery_info['delivery_tag']] = ConsumerInterface::MSG_ACK;
            } catch (\Exception $e) {
                $this->logger->critical($e);
                $result[(int) $message->delivery_info['delivery_tag']] = ConsumerInterface::MSG_REJECT;
            }
        }

        return $result;
    }

    private function deleteThumbnails(string $path)
    {
        $this->glide->deleteCache($path);
    }
}
