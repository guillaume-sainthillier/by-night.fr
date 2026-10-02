<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Picture\LegacyThumbnailRemover;
use Silarhi\PicassoBundle\Exception\PurgeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot, after the Picasso 2 release: see LegacyThumbnailRemover. Safe to re-run, a deleted directory stays
 * deleted. Previews by default, writes with --apply.
 */
#[AsCommand('app:images:remove-legacy-thumbnails', 'Delete the Picasso 1.x thumbnails of the uploads that are not event images (preview by default, --apply to write)')]
final class ImagesRemoveLegacyThumbnailsCommand extends Command
{
    public function __construct(private readonly LegacyThumbnailRemover $remover)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Delete the thumbnails. Without it the command only lists the uploads');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $paths = [...$this->remover->findPaths()];

        if ($io->isVerbose()) {
            $io->listing($paths);
        }

        if (!$input->getOption('apply')) {
            $io->info(\sprintf('%d uploads would have their 1.x thumbnails deleted (-v lists them). Run with --apply to delete them.', \count($paths)));

            return Command::SUCCESS;
        }

        $failures = [];
        foreach ($io->progressIterate($paths) as $path) {
            try {
                $this->remover->remove($path);
            } catch (PurgeException $e) {
                $failures[] = \sprintf('%s: %s', $path, $e->getPrevious()?->getMessage() ?? $e->getMessage());
            }
        }

        if ([] !== $failures) {
            $io->error(\sprintf('%d of %d uploads failed, run the command again:', \count($failures), \count($paths)));
            $io->listing($failures);

            return Command::FAILURE;
        }

        $io->success(\sprintf('Deleted the 1.x thumbnails of %d uploads; their Cloudflare purge is queued.', \count($paths)));

        return Command::SUCCESS;
    }
}
