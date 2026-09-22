<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Event;
use App\Entity\Page;
use App\Entity\User;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Throwable;
use Vich\UploaderBundle\Mapping\PropertyMappingFactoryInterface;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

#[AsCommand(
    name: 'app:images:backfill-dimensions',
    description: 'Backfill the missing width/height of uploaded images (events, users, pages) by reading the stored files',
)]
final class ImagesBackfillDimensionsCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 50;

    /**
     * Vich file properties per entity, with the embedded File each one fills.
     *
     * The file property ('imageFile') is what Vich's mapping and storage APIs address;
     * the embedded property ('image') is what the DQL filter addresses (o.image.dimensions).
     *
     * @var array<string, array{class: class-string<Event|User|Page>, fields: array<string, string>}>
     */
    private const array TARGETS = [
        'event' => ['class' => Event::class, 'fields' => ['imageFile' => 'image', 'imageSystemFile' => 'imageSystem']],
        'user' => ['class' => User::class, 'fields' => ['imageFile' => 'image', 'imageSystemFile' => 'imageSystem']],
        'page' => ['class' => Page::class, 'fields' => ['imageFile' => 'image']],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyMappingFactoryInterface $mappingFactory,
        private readonly StorageInterface $storage,
        /** The Flysystem storages the Vich mappings upload to, by service id */
        #[AutowireLocator([
            'users.storage' => new Autowire(service: 'users.storage'),
            'events.storage' => new Autowire(service: 'events.storage'),
            'pages.storage' => new Autowire(service: 'pages.storage'),
        ])]
        private readonly ContainerInterface $filesystems,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('entity', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, \sprintf('Restrict to some entities (%s)', implode(', ', array_keys(self::TARGETS))))
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Read the stored files and report, without writing anything')
            ->addOption('clear-missing', null, InputOption::VALUE_NONE, 'Erase the image (and its hash) of the rows whose stored file cannot be read')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows loaded per flush', (string) self::DEFAULT_BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Backfilling image dimensions');

        $dryRun = (bool) $input->getOption('dry-run');
        $clearMissing = (bool) $input->getOption('clear-missing');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        /** @var string[] $names */
        $names = (array) $input->getOption('entity') ?: array_keys(self::TARGETS);
        $unknown = array_diff($names, array_keys(self::TARGETS));
        if ([] !== $unknown) {
            $io->error(\sprintf('Unknown entity "%s", expected one of: %s.', implode('", "', $unknown), implode(', ', array_keys(self::TARGETS))));

            return Command::INVALID;
        }

        if ($dryRun) {
            $io->note('Dry run: the stored files are read, nothing is written.');
        }

        $rows = [];
        foreach ($names as $name) {
            ['class' => $class, 'fields' => $fields] = self::TARGETS[$name];
            $io->section($name);

            $stats = $this->backfill($class, $fields, $batchSize, $dryRun, $clearMissing, $io);
            $rows[] = [$name, ...array_values($stats)];
        }

        $io->table(['Entity', 'Rows', 'Backfilled', 'Unreadable', 'Skipped (SVG)', 'Unreachable (kept)'], $rows);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');
        } else {
            $io->success('Image dimensions backfilled.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param class-string<Event|User|Page> $class
     * @param array<string, string>         $fields file property => embedded File property
     *
     * @return array{rows: int, backfilled: int, unreadable: int, skipped: int, unreachable: int}
     */
    private function backfill(string $class, array $fields, int $batchSize, bool $dryRun, bool $clearMissing, SymfonyStyle $io): array
    {
        $stats = ['rows' => 0, 'backfilled' => 0, 'unreadable' => 0, 'skipped' => 0, 'unreachable' => 0];

        // Keyset pagination on the id: rows leave the filter as they get fixed, so a
        // page counter would skip every other page.
        $configurations = new OrderConfigurations(
            new OrderConfiguration('o.id', static fn (Event|User|Page $entity): ?int => $entity->getId()),
        );
        /** @var CursorPagination<Event|User|Page> $pagination */
        $pagination = new CursorPagination($this->createQueryBuilder($class, $fields), $configurations, $batchSize, fetchJoinCollection: false);

        $total = \count($pagination);
        $io->text(\sprintf('%d row(s) with an image but no dimensions', $total));
        if (0 === $total) {
            return $stats;
        }

        $unreadable = [];
        Monitor::createProgressBar($total);
        foreach ($pagination->getChunkResults() as $chunk) {
            foreach ($chunk as $entity) {
                ++$stats['rows'];

                // Dimensions are not part of the indexed document: don't reindex the row.
                if ($entity instanceof Event) {
                    $entity->batchUpdate = true;
                }

                foreach (array_keys($fields) as $fileProperty) {
                    $mapping = $this->mappingFactory->fromField($entity, $fileProperty);
                    if (null === $mapping || !$this->needsDimensions($entity, $mapping)) {
                        continue;
                    }

                    // Vich itself never measures SVG files
                    if ('image/svg+xml' === $mapping->readProperty($entity, 'mimeType')) {
                        ++$stats['skipped'];
                        continue;
                    }

                    $dimensions = $this->readDimensions($entity, $mapping);
                    if (null === $dimensions) {
                        ++$stats['unreadable'];
                        $unreadable[] = \sprintf('#%d %s: %s', $entity->getId(), $fileProperty, $this->storage->resolvePath($entity, $fileProperty, null, true));
                        if ($clearMissing) {
                            if ($this->isGoneOrBroken($entity, $mapping, $fileProperty)) {
                                $this->clearUnreadableImage($entity, $mapping);
                            } else {
                                ++$stats['unreachable'];
                            }
                        }

                        continue;
                    }

                    $mapping->writeProperty($entity, 'dimensions', $dimensions);
                    ++$stats['backfilled'];
                }

                Monitor::advanceProgressBar();
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $this->entityManager->clear();
        }

        Monitor::finishProgressBar();
        $io->newLine(2);

        if ([] !== $unreadable && $io->isVerbose()) {
            $io->text($clearMissing ? 'Unreadable files, erased:' : 'Unreadable files:');
            $io->listing($unreadable);
        }

        return $stats;
    }

    /**
     * @param class-string          $class
     * @param array<string, string> $fields
     */
    private function createQueryBuilder(string $class, array $fields): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()->select('o')->from($class, 'o');

        $orX = $qb->expr()->orX();
        foreach ($fields as $embedded) {
            // simple_array stores an empty array as NULL; the '' guard only covers hand-written rows
            $orX->add(\sprintf(
                "(o.%1\$s.name IS NOT NULL AND o.%1\$s.name <> '' AND (o.%1\$s.dimensions IS NULL OR o.%1\$s.dimensions = ''))",
                $embedded,
            ));
        }

        return $qb->where($orX);
    }

    private function needsDimensions(object $entity, PropertyMappingInterface $mapping): bool
    {
        $name = $mapping->getFileName($entity);
        if (null === $name || '' === $name) {
            return false;
        }

        $dimensions = $mapping->readProperty($entity, 'dimensions');

        return !\is_array($dimensions) || [] === $dimensions;
    }

    /**
     * @return array{0: int, 1: int}|null null when the file is missing, or is not an image PHP can measure
     */
    private function readDimensions(object $entity, PropertyMappingInterface $mapping): ?array
    {
        $stream = $this->storage->resolveStream($entity, $mapping->getFilePropertyName());
        if (null === $stream) {
            return null;
        }

        try {
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        return false === $contents ? null : $this->measure($contents);
    }

    /**
     * @return array{0: int, 1: int}|null null when the bytes are not an image PHP can measure
     */
    private function measure(string $contents): ?array
    {
        if ('' === $contents) {
            return null;
        }

        // Same measurement as Vich's own upload path (AbstractStorage::upload), on the
        // bytes instead of a local path. Symfony's error handler turns the read notices
        // of a corrupt file into exceptions, hence the catch.
        try {
            $info = @getimagesizefromstring($contents);
        } catch (Throwable) {
            return null;
        }

        return false === $info ? null : [$info[0], $info[1]];
    }

    /**
     * Vich's resolveStream() answers null for any storage error (a timeout, a 5xx, a 403) as it
     * does for a missing object: before erasing, the file must be confirmed gone, or read back
     * and still not be an image. When the storage cannot tell, the image is kept, as nothing
     * could bring an upload back once erased (app:storage:cleanup then deletes its file).
     */
    private function isGoneOrBroken(object $entity, PropertyMappingInterface $mapping, string $fileProperty): bool
    {
        $filesystem = $this->filesystems->get((string) $mapping->getUploadDestination());
        \assert($filesystem instanceof FilesystemOperator);
        $path = (string) $this->storage->resolvePath($entity, $fileProperty, null, true);

        try {
            if (!$filesystem->fileExists($path)) {
                return true;
            }

            return null === $this->measure($filesystem->read($path));
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Erases a row's image whose stored file cannot be read: the object is missing from the
     * bucket, or its bytes are not an image PHP can measure. Only reached with --clear-missing.
     *
     * Blanking the embedded File (name, size, mimeType, originalName, dimensions) turns
     * hasImage() false, so the placeholder or the entity's other image takes over. The hash
     * goes with it: an erased Event::imageSystem is fetched again by app:events:download-images,
     * but EventHandler::uploadFile() skips a download whose hash still matches. Event::image
     * and User::image are user uploads nothing can bring back; erasing them only stops a
     * broken <img> from rendering.
     */
    private function clearUnreadableImage(Event|User|Page $entity, PropertyMappingInterface $mapping): void
    {
        $mapping->erase($entity);

        if ($entity instanceof Page) {
            return;
        }

        match ($mapping->getFilePropertyName()) {
            'imageFile' => $entity->setImageHash(null),
            'imageSystemFile' => $entity->setImageSystemHash(null),
            default => null,
        };
    }
}
