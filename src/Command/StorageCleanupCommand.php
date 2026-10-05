<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Storage\OrphanedUploadsCleaner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:storage:cleanup',
    description: 'Remove orphaned files from S3 storage (files not referenced by any entity)',
)]
final class StorageCleanupCommand extends Command
{
    private const int DEFAULT_BATCH_SIZE = 1000;

    public function __construct(
        private readonly OrphanedUploadsCleaner $cleaner,
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
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        if ($dryRun) {
            $io->note('Running in dry-run mode. No files will be deleted.');
        }

        $orphanedFiles = 0;
        $orphanedSize = 0;
        $orphans = $this->cleaner->findOrphans($batchSize);
        foreach ($orphans as $orphan) {
            ++$orphanedFiles;
            $orphanedSize += $orphan['size'];

            if ($io->isVerbose()) {
                $io->writeln(\sprintf('  [%s] %s', $dryRun ? 'DRY-RUN' : 'DELETE', $orphan['key']));
            }

            if (!$dryRun) {
                $this->cleaner->delete($orphan['key']);
            }
        }

        $io->newLine();

        if ($orphanedFiles > 0) {
            $message = \sprintf(
                '%s %d orphaned files (%.2f MB) out of %d total files',
                $dryRun ? 'Would delete' : 'Deleted',
                $orphanedFiles,
                $orphanedSize / 1_048_576,
                $orphans->getReturn(),
            );
            $dryRun ? $io->info($message) : $io->success($message);
        } else {
            $io->success('No orphaned files found.');
        }

        return Command::SUCCESS;
    }
}
