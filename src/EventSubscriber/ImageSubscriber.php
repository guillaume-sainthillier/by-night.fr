<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Entity\User;
use App\Message\PurgeCdnCacheUrl;
use App\Message\RemoveImageThumbnails;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\MessageBusInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

final class ImageSubscriber implements EventSubscriberInterface
{
    private array $paths = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Events::PRE_REMOVE => 'onImageDelete',
            Events::POST_REMOVE => 'onImageDeleted',
            Events::PRE_UPLOAD => 'onImageUpload',
        ];
    }

    // Remove manual uploads from container
    public function onImageUpload(Event $event): void
    {
        // file become an instance of File just after upload, we have to track it before the change
        $file = $event->getMapping()->getFile($event->getObject());

        // Extract metadatas
        $object = $event->getObject();
        if ($object instanceof User || $object instanceof \App\Entity\Event) {
            try {
                [
                    'checksum' => $checksum,
                ] = $this->getImageMetadata($file);

                if ('imageFile' === $event->getMapping()->getFilePropertyName()) {
                    $object->setImageHash($checksum);
                } elseif ('imageSystemFile' === $event->getMapping()->getFilePropertyName()) {
                    $object->setImageSystemHash($checksum);
                }
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage(), [
                    'exception' => $exception,
                ]);
            }
        }
    }

    public function onImageDelete(Event $event): void
    {
        $object = $event->getObject();
        $mapping = $event->getMapping();

        $path = $mapping->getUriPrefix() . \DIRECTORY_SEPARATOR . $mapping->getUploadDir($object) . \DIRECTORY_SEPARATOR . $mapping->getFileName($object);
        $this->paths[] = $path;
    }

    public function onImageDeleted(): void
    {
        // Schedule thumbnails delete
        foreach ($this->paths as $path) {
            $this->messageBus->dispatch(new RemoveImageThumbnails($path));
        }

        // Schedule CDN purging of old image path
        foreach ($this->paths as $path) {
            $this->messageBus->dispatch(new PurgeCdnCacheUrl($path));
        }

        unset($this->paths);
        $this->paths = [];
    }

    private function getImageMetadata(File $file): array
    {
        return [
            'checksum' => md5_file($file->getPathname()),
        ];
    }
}
