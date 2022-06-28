<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\File\DeletableFile;
use App\Producer\PurgeCdnCacheUrlProducer;
use App\Producer\RemoveImageThumbnailsProducer;
use const DIRECTORY_SEPARATOR;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

class ImageSubscriber implements EventSubscriberInterface
{
    private array $paths = [];

    /** @var DeletableFile[] */
    private array $files = [];

    public function __construct(private PurgeCdnCacheUrlProducer $purgeCdnCacheUrlProducer, private RemoveImageThumbnailsProducer $removeImageThumbnailsProducer)
    {
    }

    /**
     * {@inheritdoc}
     */
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
    public function onImageUpload(Event $event): void
    {
        //file become an instance of File just after upload, we have to track it before the change
        $file = $event->getMapping()->getFile($event->getObject());
        if (null === $file || !$file instanceof DeletableFile) {
            return;
        }

        $this->files[] = $file;
    }

    public function onImageUploaded(): void
    {
        foreach ($this->files as $file) {
            $fs = new Filesystem();
            $fs->remove($file->getPathname());
        }

        unset($this->files);
        $this->files = [];
    }

    public function onImageDelete(Event $event): void
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        $path = $mapping->getUriPrefix() . DIRECTORY_SEPARATOR . $mapping->getUploadDir($object) . DIRECTORY_SEPARATOR . $mapping->getFileName($object);
        $this->paths[] = $path;
    }

    public function onImageDeleted(): void
    {
        //Schedule thumbnails delete
        foreach ($this->paths as $path) {
            $this->removeImageThumbnailsProducer->scheduleRemove($path);
        }

        //Schedule CDN purging of old image path
        foreach ($this->paths as $path) {
            $this->purgeCdnCacheUrlProducer->schedulePurge($path);
        }

        unset($this->paths);
        $this->paths = [];
    }
}
