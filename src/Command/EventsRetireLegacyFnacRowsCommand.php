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
 * Hands each Fnac show back to the row the feed still updates.
 *
 * Until #407 (2026-06-01) the Fnac parser stored one row per ticket product, under its
 * merchant_product_id. It now stores one row per show, under sha1(name|place), and never
 * publishes the old ids again. The identity backfill put both kinds of rows in the same
 * families, and the oldest row, one of the old scheme, stayed canonical: the page kept its
 * June price, description and status while the import updated a duplicate redirecting to it
 * (10,509 upcoming shows on 2026-09-25).
 *
 * In every family mixing both schemes, the current row becomes the canonical and the legacy
 * rows redirect to it. They lose their identity hash, like the links that predate it: they
 * stop lending their dates, so the show has the dates the feed sells now. Previews by
 * default, writes with --apply.
 */
#[AsCommand('app:events:retire-legacy-fnac-rows', 'Make the row the Fnac feed still updates the canonical of each show, the rows of the per-product scheme redirecting to it (preview by default, --apply to write)')]
final class EventsRetireLegacyFnacRowsCommand extends Command
{
    private const string ORIGIN = 'awin.fnac';

    /**
     * Length of the sha1(name|place) external ids; the merchant_product_ids are digits.
     */
    private const int CURRENT_ID_LENGTH = 40;

    /**
     * Families per flush.
     */
    private const int BATCH_SIZE = 100;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly EventFamilyResolver $familyResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $hashes = $this->findMixedFamilies();
        $io->section(\sprintf('%s %d Fnac show(s) with rows of both id schemes', $apply ? 'Fixing' : 'Previewing', \count($hashes)));

        $promoted = 0;
        $retired = 0;
        $sample = [];
        $io->progressStart(\count($hashes));
        foreach (array_chunk($hashes, self::BATCH_SIZE) as $chunk) {
            $families = [];
            foreach ($this->eventRepository->findAllByIdentityHashes($chunk) as $member) {
                $families[(string) $member->getIdentityHash()][] = $member;
            }

            $memberIds = [];
            foreach ($families as $members) {
                $current = array_values(array_filter($members, self::isCurrent(...)));
                if ([] === $current) {
                    continue;
                }

                // A current row already heading the family keeps its role, else the oldest one takes it
                $canonical = array_find($current, static fn (Event $member): bool => null === $member->getDuplicateOf()) ?? $current[0];
                $former = $canonical->getDuplicateOf();
                if (null !== $former) {
                    if (\count($sample) < 10) {
                        $sample[] = [$canonical->getName(), \sprintf('%d: %s', $former->getId(), $former->getPrices()), \sprintf('%d: %s', $canonical->getId(), $canonical->getPrices())];
                    }

                    $canonical->setDuplicateOf(null);
                    ++$promoted;
                }

                foreach ($members as $member) {
                    $memberIds[] = (int) $member->getId();
                    if ($member === $canonical) {
                        continue;
                    }

                    $member->setDuplicateOf($canonical);
                    if (!self::isCurrent($member)) {
                        $member->setIdentityHash(null);
                        ++$retired;
                    }
                }
            }

            if ($apply) {
                $this->entityManager->flush();
                $this->entityManager->clear();

                // Rebuilds the dates of the new canonicals and drops the ones the former
                // canonicals inherited
                $this->familyResolver->resolveForEvents($memberIds);
            }

            $this->entityManager->clear();
            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(['Show', 'Canonical until now', 'Canonical from now on'], $sample);

        if ($apply) {
            $io->success(\sprintf('%d show(s) handed to their current row, %d legacy row(s) redirected to it.', $promoted, $retired));
        } else {
            $io->note(\sprintf('%d show(s) would be handed to their current row, %d legacy row(s) redirected to it. Preview only, nothing was written: re-run with --apply.', $promoted, $retired));
        }

        return Command::SUCCESS;
    }

    /**
     * The identity hashes of the Fnac families holding rows of both id schemes.
     *
     * @return list<string>
     */
    private function findMixedFamilies(): array
    {
        return $this->eventRepository
            ->createQueryBuilder('e')
            ->select('e.identityHash')
            ->where('e.externalOrigin = :origin')
            ->andWhere('e.identityHash IS NOT NULL')
            ->groupBy('e.identityHash')
            ->having('SUM(CASE WHEN LENGTH(e.externalId) = :length THEN 1 ELSE 0 END) > 0')
            ->andHaving('SUM(CASE WHEN LENGTH(e.externalId) = :length THEN 0 ELSE 1 END) > 0')
            ->orderBy('e.identityHash', 'ASC')
            ->setParameter('origin', self::ORIGIN)
            ->setParameter('length', self::CURRENT_ID_LENGTH)
            ->getQuery()
            ->getSingleColumnResult();
    }

    private static function isCurrent(Event $event): bool
    {
        return self::CURRENT_ID_LENGTH === \strlen((string) $event->getExternalId());
    }
}
