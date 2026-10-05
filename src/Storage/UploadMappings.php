<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Storage;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\Metadata\MetadataReaderInterface;

/**
 * The files Vich stores in the bucket, as its mappings describe them (config/packages/vich_uploader.yaml): the folder
 * of each mapping, and the columns naming its files. A new mapping is followed without anything to add here.
 *
 * A mapping's folder in the bucket is its public path ("/uploads/documents" is "uploads/documents"): the storages of
 * the uploads keep it as their prefix (config/packages/flysystem.yaml).
 */
final readonly class UploadMappings
{
    /**
     * @param array<string, array{uri_prefix: string}> $mappings
     */
    public function __construct(
        #[Autowire(service: 'vich_uploader.metadata_reader')]
        private MetadataReaderInterface $metadataReader,
        #[Autowire(param: 'vich_uploader.mappings')]
        private array $mappings,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string> the folders of the bucket holding the uploads: "uploads/documents", "uploads/users"…
     */
    public function getFolders(): array
    {
        return array_values(array_unique(array_map(
            static fn (array $mapping): string => trim($mapping['uri_prefix'], '/'),
            $this->mappings,
        )));
    }

    /**
     * @return list<array{table: string, column: string}> the columns storing the name of an uploaded file
     */
    public function getNameColumns(): array
    {
        $columns = [];
        foreach ($this->metadataReader->getUploadableClasses() as $class) {
            if (!\is_string($class) || !class_exists($class)) {
                continue;
            }

            $metadata = $this->entityManager->getClassMetadata($class);
            /** @var array{fileNameProperty: string} $field */
            foreach ($this->metadataReader->getUploadableFields($class) as $field) {
                $columns[] = ['table' => $metadata->getTableName(), 'column' => $metadata->getColumnName($field['fileNameProperty'])];
            }
        }

        return array_values(array_unique($columns, \SORT_REGULAR));
    }

    /**
     * Where a file of the bucket belongs: its mapping (also the Picasso loader serving it), the public path of that
     * mapping, and its path there ("2026/06/12/a.jpg", "a.jpg" for a mapping without directories), the one Vich resolves
     * and Picasso keys its thumbnails with.
     *
     * @return array{mapping: string, uriPrefix: string, path: string}|null null out of every mapping's folder
     */
    public function locate(string $key): ?array
    {
        foreach ($this->mappings as $name => $mapping) {
            $folder = trim($mapping['uri_prefix'], '/');
            if (str_starts_with($key, $folder . '/')) {
                return ['mapping' => $name, 'uriPrefix' => '/' . $folder, 'path' => substr($key, \strlen($folder) + 1)];
            }
        }

        return null;
    }
}
