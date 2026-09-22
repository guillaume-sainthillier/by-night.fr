<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Entity\Place;
use App\Entity\PlaceMetadata;
use App\Entity\PlaceNameSlug;
use App\Repository\PlaceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the places no event points to any more, with their identities and name slugs.
 *
 * A place only comes into being with an event and is left behind when its events are
 * deleted or move elsewhere (an import that starts naming venues, a merge). Such a place
 * still answers on /agenda/sortir-a/{slug} with an empty page and keeps matching new
 * imports by name, so it is better gone. Recently created places are kept (--min-age) and
 * every place is re-checked inside the deleting transaction, so an event attached since
 * the list was built saves its place. Previews by default, writes with --apply.
 */
#[AsCommand('app:places:remove-eventless', 'Delete the places no event points to any more (preview by default, --apply to write)')]
final class PlacesRemoveEventlessCommand extends Command
{
    private const int BATCH_SIZE = 500;

    private const int PREVIEW_SAMPLE = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlaceRepository $placeRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the deletions. Without it the command only prints what it would do')
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'Only the places carrying an identity from this external origin (e.g. datatourisme)')
            ->addOption('min-age', null, InputOption::VALUE_REQUIRED, 'Keep the places created less than this long ago, in any relative date format ("12 hours", "0 second")', '1 day');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        /** @var string|null $origin */
        $origin = $input->getOption('origin');
        /** @var string $minAge */
        $minAge = $input->getOption('min-age');

        try {
            $createdBefore = new DateTimeImmutable(\sprintf('-%s', $minAge));
        } catch (Exception) {
            $io->error(\sprintf('"%s" is not a relative date, try "1 day" or "12 hours".', $minAge));

            return Command::INVALID;
        }

        $ids = $this->placeRepository->findEventlessIds($origin, $createdBefore);
        if ([] === $ids) {
            $io->success('No event-less place to remove.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf(
            '%s %d event-less place(s) created before %s%s',
            $apply ? 'Deleting' : 'Previewing',
            \count($ids),
            $createdBefore->format('Y-m-d H:i'),
            null === $origin ? '' : \sprintf(', known to %s', $origin),
        ));
        $this->showSample($io, $ids);

        $totals = ['places' => 0, 'identities' => 0, 'slugs' => 0, 'skipped' => 0];
        $io->progressStart(\count($ids));
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            $result = $apply
                ? $this->entityManager->wrapInTransaction(fn (): array => $this->process($chunk, true))
                : $this->process($chunk, false);

            foreach ($result as $key => $value) {
                $totals[$key] += $value;
            }

            $io->progressAdvance(\count($chunk));
        }

        $io->progressFinish();
        $io->table(
            ['Places', 'Identities', 'Name slugs', 'Skipped (got an event meanwhile)'],
            [[$totals['places'], $totals['identities'], $totals['slugs'], $totals['skipped']]],
        );

        if ($apply) {
            $io->success(\sprintf('%d place(s) removed.', $totals['places']));
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to delete.');
        }

        return Command::SUCCESS;
    }

    /**
     * Re-checks the chunk, counts what goes with it and, when applying, deletes the
     * children first so no foreign key is left dangling.
     *
     * @param list<int> $chunk
     *
     * @return array{places: int, identities: int, slugs: int, skipped: int}
     */
    private function process(array $chunk, bool $apply): array
    {
        $ids = $this->placeRepository->onlyEventless($chunk);

        $result = [
            'places' => \count($ids),
            'identities' => $this->countRows(PlaceMetadata::class, $ids),
            'slugs' => $this->countRows(PlaceNameSlug::class, $ids),
            'skipped' => \count($chunk) - \count($ids),
        ];

        if ($apply && [] !== $ids) {
            $this->deleteRows(PlaceMetadata::class, 'place', $ids);
            $this->deleteRows(PlaceNameSlug::class, 'place', $ids);
            $this->deleteRows(Place::class, 'id', $ids);
        }

        return $result;
    }

    /**
     * @param list<int> $ids
     */
    private function showSample(SymfonyStyle $io, array $ids): void
    {
        /** @var Place[] $places */
        $places = $this->placeRepository->findBy(['id' => \array_slice($ids, 0, self::PREVIEW_SAMPLE)], ['id' => 'ASC']);

        $io->table(
            ['Id', 'Name', 'City', 'Created'],
            array_map(static fn (Place $place): array => [
                $place->getId(),
                $place->getName(),
                $place->getCity()?->getName() ?? $place->getCityName(),
                $place->getCreatedAt()?->format('Y-m-d'),
            ], $places),
        );

        if (\count($ids) > self::PREVIEW_SAMPLE) {
            $io->text(\sprintf('… and %d more.', \count($ids) - self::PREVIEW_SAMPLE));
        }

        // Nothing below works on hydrated objects
        $this->entityManager->clear();
    }

    /**
     * @param class-string $class
     * @param list<int>    $placeIds
     */
    private function countRows(string $class, array $placeIds): int
    {
        if ([] === $placeIds) {
            return 0;
        }

        return (int) $this
            ->entityManager
            ->createQueryBuilder()
            ->select('COUNT(x.id)')
            ->from($class, 'x')
            ->where('x.place IN (:ids)')
            ->setParameter('ids', $placeIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param class-string $class
     * @param list<int>    $ids
     */
    private function deleteRows(string $class, string $field, array $ids): void
    {
        $this
            ->entityManager
            ->createQueryBuilder()
            ->delete($class, 'x')
            ->where(\sprintf('x.%s IN (:ids)', $field))
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
