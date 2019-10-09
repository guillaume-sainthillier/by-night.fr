<?php

namespace App\EventListener;

use App\Consumer\RemoveImageThumbnailsConsumer;
use App\Entity\Event;
use App\Entity\User;
use App\File\DeletableFile;
use App\Producer\PurgeCdnCacheUrlProducer;
use App\Producer\RemoveImageThumbnailsProducer;
use GuzzleHttp\Client;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Event\Events;
use function GuzzleHttp\Promise\all;

class ImageListener implements EventSubscriberInterface
{
    /** @var array */
    private $paths;

    /** @var DeletableFile[] */
    private $files;

    /** @var PurgeCdnCacheUrlProducer */
    private $purgeCdnCacheUrlProducer;

    /** @var RemoveImageThumbnailsProducer */
    private $removeImageThumbnailsProducer;

    public function __construct(PurgeCdnCacheUrlProducer $purgeCdnCacheUrlProducer, RemoveImageThumbnailsProducer $removeImageThumbnailsProducer)
    {
        $this->paths = [];
        $this->files = [];
        $this->purgeCdnCacheUrlProducer = $purgeCdnCacheUrlProducer;
        $this->removeImageThumbnailsProducer = $removeImageThumbnailsProducer;
    }

    public static function getSubscribedEvents()
    {
        return [
            Events::PRE_REMOVE => 'onImageDelete',
            Events::POST_REMOVE => 'onImageDeleted',
            Events::PRE_UPLOAD => 'onImageUpload',
            Events::POST_UPLOAD => 'onImageUploaded',
        ];
    }

    // Remove manual uploads from container
    public function onImageUpload(\Vich\UploaderBundle\Event\Event $event)
    {
        //file become an instance of File just after upload, we have to track it before the change
        $file = $event->getMapping()->getFile($event->getObject());
        if (null === $file || !$file instanceof DeletableFile) {
            return;
        }

        $this->files[] = $file;
    }

    public function onImageUploaded()
    {
        foreach($this->files as $file) {
            $fs = new Filesystem();
            $fs->remove($file->getPathname());
        }

        unset($this->files);
        $this->files = [];
    }

    public function onImageDelete(\Vich\UploaderBundle\Event\Event $event)
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        $path = $mapping->getUriPrefix() . \DIRECTORY_SEPARATOR . $mapping->getUploadDir($object) . \DIRECTORY_SEPARATOR . $mapping->getFileName($object);
        $this->paths[] = $path;
    }

    public function onImageDeleted()
    {
        //Schedule thumbnails delete
        foreach($this->paths as $path) {
            $this->removeImageThumbnailsProducer->scheduleRemove($path);
        }

        //Schedule CDN purging of old image path
        foreach($this->paths as $path) {
            $this->purgeCdnCacheUrlProducer->schedulePurge('path');
        }

        unset($this->paths);
        $this->paths = [];
    }
}
