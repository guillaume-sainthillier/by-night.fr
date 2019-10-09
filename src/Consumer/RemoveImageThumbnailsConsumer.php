<?php

namespace App\Consumer;

use App\Producer\PurgeCdnCacheUrlProducer;
use League\Glide\Server;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RemoveImageThumbnailsConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    /** @var Server */
    private $glide;

    /** @var PurgeCdnCacheUrlProducer */
    private $purgeCdnCacheUrlProducer;

    public function __construct(LoggerInterface $logger, Server $glide, PurgeCdnCacheUrlProducer $purgeCdnCacheUrlProducer)
    {
        parent::__construct($logger);

        $this->glide = $glide;
        $this->purgeCdnCacheUrlProducer = $purgeCdnCacheUrlProducer;
    }

    public function batchExecute(array $messages)
    {
        $result = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $path) {
            try {
                $this->deleteThumbnails($path);
                $result[(int)$message->delivery_info['delivery_tag']] = ConsumerInterface::MSG_ACK;
            } catch (\Exception $e) {
                $this->logger->critical($e);
                $result[(int)$message->delivery_info['delivery_tag']] = ConsumerInterface::MSG_REJECT;
            }
        }

        return $result;
    }

    private function deleteThumbnails(string $path)
    {
        $this->glide->deleteCache($path);
    }
}
