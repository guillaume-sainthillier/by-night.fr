<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Message\PurgeCdnCacheUrl;
use App\Message\RemoveImageThumbnails;
use Aws\S3\S3Client;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Generator;
use Silarhi\CursorPagination\Iterator\ChunkIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:storage:cleanup',
    description: 'Remove orphaned files from S3 storage (files not referenced by any entity)',
)]
final class StorageCleanupCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 1000;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 's3_client')]
        private readonly S3Client $s3Client,
        #[Autowire(env: 'S3_BUCKET_NAME')]
        private readonly string $bucketName,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview deletions without executing')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of S3 files per DB check', (string) self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        if ($dryRun) {
            $io->note('Running in dry-run mode. No files will be deleted.');
        }

        $totalFiles = 0;
        $orphanedFiles = 0;
        $orphanedSize = 0;

        $chunks = new ChunkIterator($this->listAllFiles(), $batchSize);

        /** @var array<string, array{key: string, size: int}> $chunk */
        foreach ($chunks as $chunk) {
            $totalFiles += \count($chunk);
            $stats = $this->processBatch($chunk, $dryRun, $io);
            $orphanedFiles += $stats['orphanedFiles'];
            $orphanedSize += $stats['orphanedSize'];
        }

        $io->newLine();

        if ($orphanedFiles > 0) {
            $message = \sprintf(
                '%s %d orphaned files (%.2f MB) out of %d total files',
                $dryRun ? 'Would delete' : 'Deleted',
                $orphanedFiles,
                $orphanedSize / 1_048_576,
                $totalFiles,
            );
            $dryRun ? $io->info($message) : $io->success($message);
        } else {
            $io->success('No orphaned files found.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{key: string, size: int}> $batch basename => entry
     *
     * @return array{orphanedFiles: int, orphanedSize: int}
     */
    private function processBatch(array $batch, bool $dryRun, SymfonyStyle $io): array
    {
        $orphanedFiles = 0;
        $orphanedSize = 0;

        $basenames = array_keys($batch);
        $referencedPaths = $this->findReferencedPaths($basenames);

        foreach ($batch as $basename => $entry) {
            if (isset($referencedPaths[$basename])) {
                continue;
            }

            ++$orphanedFiles;
            $orphanedSize += $entry['size'];
            $path = $entry['key'];

            if ($io->isVerbose()) {
                $io->writeln(\sprintf(
                    '  [%s] %s',
                    $dryRun ? 'DRY-RUN' : 'DELETE',
                    $path,
                ));
            }

            if (!$dryRun) {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $path,
                ]);

                $imageCachePath = str_replace([
                    'uploads/documents',
                    'uploads/users',
                    'uploads/pages',
                ], '', $path);
                $this->messageBus->dispatch(new RemoveImageThumbnails(ltrim($imageCachePath, '/')));
                $this->messageBus->dispatch(new PurgeCdnCacheUrl('/' . ltrim($path, '/')));
            }
        }

        return [
            'orphanedFiles' => $orphanedFiles,
            'orphanedSize' => $orphanedSize,
        ];
    }

    /**
     * @return Generator<string, array{key: string, size: int}>
     */
    private function listAllFiles(): Generator
    {
        $paginator = $this->s3Client->getPaginator('ListObjectsV2', [
            'Bucket' => $this->bucketName,
            'Prefix' => 'uploads/',
        ]);

        foreach ($paginator as $page) {
            /** @var array{Key: string, Size: int} $object */
            foreach ($page['Contents'] ?? [] as $object) {
                yield basename($object['Key']) => [
                    'key' => $object['Key'],
                    'size' => $object['Size'],
                ];
            }
        }
    }

    /**
     * @param list<string> $basenames
     *
     * @return array<string, true>
     */
    private function findReferencedPaths(array $basenames): array
    {
        $sql = <<<'SQL'
            SELECT image_name AS path FROM `event` WHERE image_name IN (:names)
            UNION
            SELECT image_system_name AS path FROM `event` WHERE image_system_name IN (:names)
            UNION
            SELECT image_name AS path FROM `user` WHERE image_name IN (:names)
            UNION
            SELECT image_system_name AS path FROM `user` WHERE image_system_name IN (:names)
            UNION
            SELECT image_name AS path FROM `page` WHERE image_name IN (:names)
            SQL;

        $result = $this->connection->executeQuery($sql, [
            'names' => $basenames,
        ], [
            'names' => ArrayParameterType::STRING,
        ]);

        $referenced = [];
        while (false !== ($path = $result->fetchOne())) {
            $referenced[$path] = true;
        }

        return $referenced;
    }
}
