<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Import\CrossSource\CrossSourceDuplicateFinder;
use App\Import\CrossSource\CrossSourceGroup;
use App\Import\CrossSource\CrossSourceGrouper;
use App\Import\CrossSource\CrossSourcePair;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports the shows two sources import at the same venue (CrossSourceDuplicateFinder), to check the matching rules on
 * real data before anything is linked. Writes nothing to the database.
 */
#[AsCommand('app:events:cross-source-report', 'Report the shows several sources import at the same venue (read only)')]
final class EventsCrossSourceReportCommand extends Command
{
    public function __construct(
        private readonly CrossSourceDuplicateFinder $finder,
        private readonly CrossSourceGrouper $grouper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('place', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these venue ids')
            ->addOption('sample', null, InputOption::VALUE_REQUIRED, 'How many matched pairs to print', '30')
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Write every pair whose titles agree to this CSV file, with the verdict of the full match');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = new DateTimeImmutable('today');
        /** @var list<string> $places */
        $places = $input->getOption('place');
        $placeIds = [] !== $places ? array_map(intval(...), $places) : $this->finder->findPlaceIds($from);

        /** @var string|null $csvPath */
        $csvPath = $input->getOption('csv');
        $csv = null !== $csvPath ? fopen($csvPath, 'w') : null;
        if (false === $csv) {
            $io->error(\sprintf('Cannot write "%s".', $csvPath));

            return Command::FAILURE;
        }

        if (null !== $csv) {
            fputcsv($csv, ['verdict', 'left_id', 'left_source', 'left_name', 'right_id', 'right_source', 'right_name', 'place', 'start_date'], escape: '');
        }

        $io->section(\sprintf('Scanning %d venue(s) listed by several sources', \count($placeIds)));

        $verdicts = [];
        $bySources = [];
        /** @var list<CrossSourcePair> $matches */
        $matches = [];
        $io->progressStart(\count($placeIds));
        foreach ($this->finder->find($placeIds, $from) as $scanned => $pairs) {
            foreach ($pairs as $pair) {
                $verdicts[$pair->verdict->value] = ($verdicts[$pair->verdict->value] ?? 0) + 1;
                if ($pair->verdict->isMatch()) {
                    $sources = [$pair->leftSource, $pair->rightSource];
                    sort($sources);
                    $key = implode(' + ', $sources);
                    $bySources[$key] = ($bySources[$key] ?? 0) + 1;
                    $matches[] = $pair;
                }

                if (null !== $csv) {
                    fputcsv($csv, [$pair->verdict->value, $pair->leftId, $pair->leftSource, $pair->leftName, $pair->rightId, $pair->rightSource, $pair->rightName, $pair->placeName, $pair->startDate?->format('Y-m-d')], escape: '');
                }
            }

            $io->progressAdvance($scanned);
        }

        $io->progressFinish();
        if (null !== $csv) {
            fclose($csv);
        }

        arsort($verdicts);
        $io->table(['Verdict of the pairs whose titles agree', 'Pairs'], array_map(null, array_keys($verdicts), array_values($verdicts)));

        arsort($bySources);
        $io->table(['Sources of the matched pairs', 'Pairs'], array_map(null, array_keys($bySources), array_values($bySources)));

        $sample = (int) $input->getOption('sample');
        $this->reportGroups($io, $matches, $sample);

        if ($sample > 0 && [] !== $matches) {
            shuffle($matches);
            $io->table(
                ['Verdict', 'Left', 'Right', 'Venue', 'Date'],
                array_map(static fn (CrossSourcePair $pair): array => [
                    $pair->verdict->value,
                    \sprintf('%s (%s #%d)', $pair->leftName, $pair->leftSource, $pair->leftId),
                    \sprintf('%s (%s #%d)', $pair->rightName, $pair->rightSource, $pair->rightId),
                    $pair->placeName,
                    $pair->startDate?->format('Y-m-d'),
                ], \array_slice($matches, 0, $sample)),
            );
        }

        $io->note('Read only: nothing was linked.');

        return Command::SUCCESS;
    }

    /**
     * The shows the matched pairs add up to. Only the consistent ones could be linked; the others are printed, since
     * they are the false positives a title matching an artist alone would chain.
     *
     * @param list<CrossSourcePair> $matches
     */
    private function reportGroups(SymfonyStyle $io, array $matches, int $sample): void
    {
        $groups = $this->grouper->group($matches);

        $sizes = [];
        $inconsistent = [];
        $severalOfOneSource = 0;
        foreach ($groups as $group) {
            $sizes[\count($group->members)] = ($sizes[\count($group->members)] ?? 0) + 1;
            if (!$group->isConsistent()) {
                $inconsistent[] = $group;
            } elseif ($group->hasSeveralEventsOfOneSource()) {
                ++$severalOfOneSource;
            }
        }

        ksort($sizes);
        $io->table(['Events per show', 'Shows'], array_map(null, array_keys($sizes), array_values($sizes)));
        $io->text([
            \sprintf('%d show(s), %d event(s) in them.', \count($groups), array_sum(array_map(static fn (CrossSourceGroup $group): int => \count($group->members), $groups))),
            \sprintf('%d consistent show(s) hold several events of one source (it lists the show twice).', $severalOfOneSource),
            \sprintf('%d inconsistent show(s): two of their titles name two shows, they would be left apart.', \count($inconsistent)),
        ]);

        shuffle($inconsistent);
        foreach (\array_slice($inconsistent, 0, $sample) as $group) {
            $io->listing(array_map(
                static fn (int $id, array $member): string => \sprintf('#%d %s: %s', $id, $member['source'], $member['name']),
                array_keys($group->members),
                $group->members,
            ));
        }
    }
}
