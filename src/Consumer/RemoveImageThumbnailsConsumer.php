<?php

namespace App\Consumer;

use App\Producer\PurgeCdnCacheUrlProducer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class RemoveImageThumbnailsConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    /** @var CacheManager */
    private $cacheManager;

    /** @var PurgeCdnCacheUrlProducer */
    private $purgeCdnCacheUrlProducer;

    public function __construct(LoggerInterface $logger, CacheManager $cacheManager, PurgeCdnCacheUrlProducer $purgeCdnCacheUrlProducer)
    {
        parent::__construct($logger);

        $this->cacheManager = $cacheManager;
        $this->purgeCdnCacheUrlProducer = $purgeCdnCacheUrlProducer;
    }

    public function batchExecute(array $messages)
    {
        $result = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $message) {
            $path = \json_decode($message->getBody(), true);

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

    private function deleteThumbnails(array $path)
    {
        $existingFilters = [];
        $resolvedPaths = [];
        foreach ($path['filters'] as $filter) {
            if ($this->cacheManager->isStored($path['path'], $filter)) {
                $resolvedPaths[] = $this->cacheManager->resolve($path['path'], $filter);
                $existingFilters[] = $filter;
            }
        }

        if (!$existingFilters) {
            return;
        }

        //Optimize call
        $this->cacheManager->remove($path['path'], $existingFilters);

        //Schedule thumbnails image purge in cdn cache
        foreach ($resolvedPaths as $resolvedPath) {
            $this->purgeCdnCacheUrlProducer->schedulePurge($resolvedPath);
        }
    }
}
