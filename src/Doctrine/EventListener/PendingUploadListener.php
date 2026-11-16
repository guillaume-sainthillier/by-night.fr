<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\Handler\UploadHandler;
use Vich\UploaderBundle\Metadata\MetadataReader;

/**
 * Uploads the files given to a managed entity before Doctrine computes its change set. VichUploader uploads them on
 * `preUpdate`, which Doctrine only dispatches for an entity that changed: the entities used to bump their `updatedAt`
 * when given a file, but since doctrine/orm 3.8 a date that maps to the same database value is no change, so a file
 * given within the second of the last update was lost. Uploading here sets the file's name, size... on the entity:
 * a real change, which Vich's `preUpdate` listeners then find already done. As every mapping deletes on update, the
 * replaced file is removed first, like Vich's clean listener does.
 */
#[AsDoctrineListener(event: Events::preFlush)]
final readonly class PendingUploadListener
{
    public function __construct(
        #[Autowire(service: 'vich_uploader.metadata_reader')]
        private MetadataReader $metadataReader,
        private UploadHandler $uploadHandler,
    ) {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $identityMap = $unitOfWork->getIdentityMap();

        foreach ($this->metadataReader->getUploadableClasses() as $class) {
            foreach ($identityMap[$class] ?? [] as $entity) {
                if ($unitOfWork->isUninitializedObject($entity)) {
                    continue;
                }

                foreach (array_keys($this->metadataReader->getUploadableFields($class)) as $field) {
                    // Both do nothing without a file given since the last upload
                    $this->uploadHandler->clean($entity, $field);
                    $this->uploadHandler->upload($entity, $field);
                }
            }
        }
    }
}
