<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Storage;

use Aws\S3\S3Client;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Generator;
use Silarhi\CursorPagination\Iterator\ChunkIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The uploads of the bucket that no entity names any more, found in the folders of the Vich mappings and checked
 * against the columns naming their files (UploadMappings). A file outside those folders is not an upload of the site,
 * and is left alone.
 */
final readonly class OrphanedUploadsCleaner
{
    /**
     * Vich writes a file to the bucket during the flush that stores its name: until that
     * transaction commits (an import batch downloads its images one after the other), the file
     * looks unreferenced. Recent files are left for a later run.
     */
    private const string MIN_AGE = '24 hours';

    public function __construct(
        private Connection $connection,
        #[Autowire(service: 's3_client')]
        private S3Client $s3Client,
        #[Autowire(env: 'S3_BUCKET_NAME')]
        private string $bucketName,
        private UploadMappings $uploadMappings,
        private ImageCachePurger $imageCachePurger,
    ) {
    }

    /**
     * The orphans, checked against the database by batches of files.
     *
     * @return Generator<int, array{key: string, size: int}, mixed, int> the orphans; returns how many files were checked
     */
    public function findOrphans(int $batchSize): Generator
    {
        $files = 0;
        $sql = $this->getReferencedNamesQuery();

        /** @var array<string, array{key: string, size: int}> $chunk */
        foreach (new ChunkIterator($this->listFiles(), $batchSize) as $chunk) {
            $files += \count($chunk);
            $referenced = array_flip($this->connection->executeQuery($sql, ['names' => array_keys($chunk)], ['names' => ArrayParameterType::STRING])->fetchFirstColumn());

            foreach ($chunk as $basename => $file) {
                if (!isset($referenced[$basename])) {
                    yield $file;
                }
            }
        }

        return $files;
    }

    /**
     * Deletes a file of the bucket, and purges its thumbnails and CDN copy.
     */
    public function delete(string $key): void
    {
        $this->s3Client->deleteObject([
            'Bucket' => $this->bucketName,
            'Key' => $key,
        ]);

        $location = $this->uploadMappings->locate($key);
        if (null !== $location) {
            $this->imageCachePurger->purge($location['mapping'], $location['uriPrefix'], $location['path']);
        }
    }

    /**
     * @return Generator<string, array{key: string, size: int}> by basename, the names the columns store
     */
    private function listFiles(): Generator
    {
        $uploadedBefore = new DateTimeImmutable('-' . self::MIN_AGE);
        foreach ($this->uploadMappings->getFolders() as $folder) {
            $paginator = $this->s3Client->getPaginator('ListObjectsV2', [
                'Bucket' => $this->bucketName,
                'Prefix' => $folder . '/',
            ]);

            foreach ($paginator as $page) {
                /** @var array{Key: string, Size: int, LastModified: DateTimeInterface} $object */
                foreach ($page['Contents'] ?? [] as $object) {
                    if ($object['LastModified'] > $uploadedBefore) {
                        continue;
                    }

                    yield basename($object['Key']) => [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                    ];
                }
            }
        }
    }

    private function getReferencedNamesQuery(): string
    {
        return implode(' UNION ', array_map(
            fn (array $column): string => \sprintf(
                'SELECT %2$s FROM %1$s WHERE %2$s IN (:names)',
                $this->connection->quoteSingleIdentifier($column['table']),
                $this->connection->quoteSingleIdentifier($column['column']),
            ),
            $this->uploadMappings->getNameColumns(),
        ));
    }
}
