<?php

namespace App\EventListener;

use App\Entity\Event;
use App\Entity\User;
use GuzzleHttp\Client;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Vich\UploaderBundle\Event\Events;
use function GuzzleHttp\Promise\all;

class ImageListener implements EventSubscriberInterface
{
    /** @var CacheManager */
    private $cacheManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var Packages */
    private $packages;

    /** @var array */
    private $paths;

    /** @var Client */
    private $client;

    /** @var string */
    private $cfZone;

    public function __construct(LoggerInterface $logger, Packages $packages, CacheManager $cacheManager, string $cfUserEmail, string $cfUserKey, string $cfZone)
    {
        $this->logger = $logger;
        $this->cacheManager = $cacheManager;
        $this->packages = $packages;
        $this->cfZone = $cfZone;

        $this->paths = [
            'originals' => [],
            'filters' => []
        ];
        $this->client = new Client([
            'base_uri' => 'https://api.cloudflare.com',
            'headers' => [
                'X-Auth-Email' => $cfUserEmail,
                'X-Auth-Key' => $cfUserKey,
            ]
        ]);
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_REMOVE => 'onImageDelete',
            Events::POST_REMOVE => 'onImageDeleted',
        ];
    }

    public function onImageDelete(\Vich\UploaderBundle\Event\Event $event)
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        if ($object instanceof User) {
            $filters = ['thumb_user_large', 'thumb_user_evenement', 'thumb_user', 'thumb_user_menu', 'thumb_user_50', 'thumb_user_115'];
        } elseif ($object instanceof Event) {
            $filters = ['thumbs_evenement', 'thumb_evenement'];
        } else {
            return;
        }

        $path = $mapping->getUriPrefix() . \DIRECTORY_SEPARATOR . $mapping->getUploadDir($object) . \DIRECTORY_SEPARATOR . $mapping->getFileName($object);
        $this->paths['images'][] = $path;

        foreach ($filters as $filter) {
            if ($this->cacheManager->isStored($path, $filter)) {
                $this->paths['images'][] = $this->cacheManager->resolve($path, $filter);
                $this->paths['filters'][$filter][] = $path;
            }
        }
    }

    public function onImageDeleted()
    {
        $this->purgeThumbFiles();
        $this->purgeCloudflareCache();

        unset($this->paths);
        $this->paths = [
            'originals' => [],
            'filters' => []
        ];
    }

    private function purgeThumbFiles()
    {
        foreach ($this->paths['filters'] as $filter => $paths) {
            try {
                $this->cacheManager->remove($paths, $filter);
            } catch (\Throwable $e) {
                $this->logger->error($e);
            }
        }
    }

    private function purgeCloudflareCache()
    {
        $promises = [];
        foreach (array_chunk($this->paths['images'], 30) as $paths) {
            $FQDNPaths = [];
            foreach ($paths as $path) {
                $FQDNPaths[] = $this->packages->getUrl($path, 'file');
            }
            $promises[] = $this->client->postAsync(
                sprintf('/client/v4/zones/%s/purge_cache', $this->cfZone),
                ['json' => ["files" => $FQDNPaths]]
            );
        }

        try {
            all($promises)->wait();
        } catch (\Throwable $e) {
            $this->logger->error($e);
        }
    }
}
