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
use App\Import\EventContentHasher;
use App\Import\EventFamilyResolver;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off catch-up for the rows imported before the identity hash existed: compute
 * it from the stored columns, then resolve every family it reveals. The import does
 * both on the fly for anything it touches from now on (see EventFamilyResolver).
 */
#[AsCommand('app:events:resolve-families', 'Group the events describing the same event under distinct external ids: backfill their identity hash, then elect a canonical per family and lend it the others\' dates')]
final class EventsResolveFamiliesCommand extends Command
{
    private const int BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventContentHasher $contentHasher,
        private readonly EventFamilyResolver $familyResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'Only process events from this external origin (e.g. openagenda)')
            ->addOption('skip-backfill', null, InputOption::VALUE_NONE, 'Do not compute the missing identity hashes, only resolve the families')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows (backfill) or families (resolution) handled per flush', (string) self::BATCH_SIZE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string|null $origin */
        $origin = $input->getOption('origin');
        $batchSize = max(1, (int) $input->getOption('batch-size'));

        if (!$input->getOption('skip-backfill')) {
            $io->section('Backfilling identity hashes');
            $hashed = $this->backfillIdentityHashes($origin, $batchSize, $io);
            $io->writeln(\sprintf('  %d event(s) hashed', $hashed));
        }

        $io->section('Resolving families');
        $families = 0;
        $after = null;
        while ([] !== ($hashes = $this->eventRepository->findSharedIdentityHashesAfter($origin, $after, $batchSize))) {
            $this->familyResolver->resolveFamilies($hashes);
            $this->entityManager->clear();

            $families += \count($hashes);
            $after = end($hashes);
            $io->writeln(\sprintf('  %d families resolved so far...', $families));
        }

        $io->success(\sprintf('%d families resolved', $families));

        return Command::SUCCESS;
    }

    /**
     * Written with plain UPDATE statements: the hash is not indexed in Elasticsearch
     * and does not change the event, so neither the search index nor the timestamps
     * must react to it.
     */
    private function backfillIdentityHashes(?string $origin, int $batchSize, SymfonyStyle $io): int
    {
        $hashed = 0;
        $scanned = 0;
        $afterId = 0;

        while ([] !== ($events = $this->eventRepository->findWithoutIdentityHash($origin, $afterId, $batchSize))) {
            foreach ($events as $event) {
                $afterId = (int) $event->getId();
                ++$scanned;

                $hash = $this->contentHasher->identityOf(
                    $event->getExternalOrigin(),
                    $event->getPlaceExternalId(),
                    $event->getName(),
                    $event->getDescription(),
                );
                if (null === $hash) {
                    continue;
                }

                $this->entityManager
                    ->createQuery(\sprintf('UPDATE %s e SET e.identityHash = :hash WHERE e.id = :id', Event::class))
                    ->setParameter('hash', $hash)
                    ->setParameter('id', $event->getId())
                    ->execute();
                ++$hashed;
            }

            $this->entityManager->clear();
            $io->writeln(\sprintf('  %d row(s) scanned, %d hashed...', $scanned, $hashed));
        }

        return $hashed;
    }
}
