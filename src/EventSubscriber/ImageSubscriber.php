<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventSubscriber;

use App\Contracts\BatchResetInterface;
use App\Entity\User;
use App\Storage\ImageCachePurger;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;

final class ImageSubscriber implements EventSubscriberInterface, BatchResetInterface
{
    /** @var array<string, array{string, string, string}> their VichUploader mapping, its public path, and their path there; one purge per file */
    private array $deletedImages = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ImageCachePurger $imageCachePurger,
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

        // As Vich resolves it: a mapping without directories stores its files at its root
        $directory = (string) $mapping->getUploadDir($object);
        $fileName = (string) $mapping->getFileName($object);
        $path = '' === $directory ? $fileName : $directory . '/' . $fileName;
        $this->deletedImages[$mapping->getMappingName() . '|' . $path] = [$mapping->getMappingName(), $mapping->getUriPrefix(), $path];
    }

    public function onImageDeleted(): void
    {
        foreach ($this->deletedImages as [$mappingName, $uriPrefix, $path]) {
            $this->imageCachePurger->purge($mappingName, $uriPrefix, $path);
        }

        $this->deletedImages = [];
    }

    public function batchReset(): void
    {
        $this->deletedImages = [];
    }

    private function getImageMetadata(File $file): array
    {
        return [
            'checksum' => md5_file($file->getPathname()),
        ];
    }
}
