<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Import\CrossSource\CrossSourceGroup;
use App\Import\CrossSource\CrossSourceLinker;
use App\Import\CrossSource\CrossSourcePair;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Links the events of several sources that are the same show (CrossSourceLinker), and takes back the links their
 * sources no longer agree on. Previews by default, writes with --apply. Meant to run every night, after the imports.
 */
#[AsCommand('app:events:link-cross-source', 'Link the events of several sources that are the same show (preview by default, --apply to write)')]
final class EventsLinkCrossSourceCommand extends Command
{
    public function __construct(private readonly CrossSourceLinker $linker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the links. Without it the command only prints what it would do')
            ->addOption('place', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these venue ids')
            ->addOption('sample', null, InputOption::VALUE_REQUIRED, 'How many matched pairs and inconsistent shows to print', '0')
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Write every pair whose titles agree to this CSV file, with the verdict of the full match')
            ->addOption('keep-apart', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Part these event ids, and the other records of their event, from the events they are linked to, for good (a false match), then stop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $keepApart */
        $keepApart = $input->getOption('keep-apart');
        if ([] !== $keepApart) {
            foreach ($keepApart as $eventId) {
                $linked = $this->linker->keepApart((int) $eventId);
                $io->text([] === $linked
                    ? \sprintf('Event #%d is linked to no event.', $eventId)
                    : \sprintf('Event #%d kept apart from #%s.', $eventId, implode(', #', $linked)));
            }

            return Command::SUCCESS;
        }

        $apply = (bool) $input->getOption('apply');
        $from = new DateTimeImmutable('today');
        /** @var list<string> $places */
        $places = $input->getOption('place');
        $placeIds = [] !== $places ? array_map(intval(...), $places) : $this->linker->findPlaceIds($from);

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

        $io->section(\sprintf('%s %d venue(s) listed by several sources', $apply ? 'Linking' : 'Previewing', \count($placeIds)));

        $verdicts = [];
        $bySources = [];
        $sizes = [];
        $added = 0;
        $removed = 0;
        $shows = 0;
        $severalOfOneSource = 0;
        /** @var list<CrossSourcePair> $matches */
        $matches = [];
        /** @var list<CrossSourceGroup> $inconsistent */
        $inconsistent = [];

        $io->progressStart(\count($placeIds));
        foreach ($this->linker->link($placeIds, $from, $apply) as $result) {
            foreach ($result->chunk->pairs as $pair) {
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

            foreach ($result->groups as $group) {
                ++$shows;
                $sizes[\count($group->members)] = ($sizes[\count($group->members)] ?? 0) + 1;
                if (!$group->isConsistent()) {
                    $inconsistent[] = $group;
                } elseif ($group->hasSeveralEventsOfOneSource()) {
                    ++$severalOfOneSource;
                }
            }

            $added += $result->added;
            $removed += $result->removed;
            $io->progressAdvance($result->chunk->placeCount);
        }

        $io->progressFinish();
        if (null !== $csv) {
            fclose($csv);
        }

        arsort($verdicts);
        $io->table(['Verdict of the pairs whose titles agree', 'Pairs'], array_map(null, array_keys($verdicts), array_values($verdicts)));

        arsort($bySources);
        $io->table(['Sources of the matched pairs', 'Pairs'], array_map(null, array_keys($bySources), array_values($bySources)));

        ksort($sizes);
        $io->table(['Events per show', 'Shows'], array_map(null, array_keys($sizes), array_values($sizes)));
        $io->text([
            \sprintf('%d show(s), %d consistent of which %d hold several events of one source (it lists the show twice).', $shows, $shows - \count($inconsistent), $severalOfOneSource),
            \sprintf('%d inconsistent show(s): two of their titles name two shows, they stay unlinked.', \count($inconsistent)),
        ]);

        $sample = (int) $input->getOption('sample');
        $this->showSample($io, $matches, $inconsistent, $sample);

        $io->table(['Links ' . ($apply ? 'made' : 'to make'), 'Links ' . ($apply ? 'taken back' : 'to take back')], [[$added, $removed]]);
        if ($apply) {
            $io->success(\sprintf('%d link(s) made, %d taken back.', $added, $removed));
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to link.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<CrossSourcePair>  $matches
     * @param list<CrossSourceGroup> $inconsistent
     */
    private function showSample(SymfonyStyle $io, array $matches, array $inconsistent, int $sample): void
    {
        if ($sample <= 0) {
            return;
        }

        shuffle($inconsistent);
        foreach (\array_slice($inconsistent, 0, $sample) as $group) {
            $io->listing(array_map(
                static fn (int $id, array $member): string => \sprintf('#%d %s: %s', $id, $member['source'], $member['name']),
                array_keys($group->members),
                $group->members,
            ));
        }

        if ([] === $matches) {
            return;
        }

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
}
