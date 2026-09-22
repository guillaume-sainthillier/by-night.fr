<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Entity\Event;
use App\Entity\Tag;
use App\Messenger\TransactionalMessageDispatcher;
use App\Repository\TagRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Merges tags whose names the database considers equal (utf8mb4_unicode_ci: case,
 * accents and trailing spaces do not count) into one, which is what the unique key
 * on tag.name requires.
 *
 * The most used tag of a group is kept (the oldest on a tie); the others hand over
 * their events, as category and as theme, and are deleted. Events that changed get
 * their search document refreshed. Previews by default, writes with --apply.
 */
#[AsCommand('app:tags:merge-duplicates', 'Merge tags whose names the database considers equal into one (preview by default, --apply to write)')]
final class TagsMergeDuplicatesCommand extends Command
{
    private const int REINDEX_CHUNK_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tagRepository,
        private readonly TransactionalMessageDispatcher $messageDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the merges. Without it the command only prints what it would do');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');

        $names = $this->tagRepository->findDuplicateNames();
        if ([] === $names) {
            $io->success('Every tag name is unique.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('%s %d tag names carried by several tags', $apply ? 'Merging' : 'Previewing', \count($names)));

        $totals = ['tagsRemoved' => 0, 'categoriesMoved' => 0, 'themesMoved' => 0, 'eventsReindexed' => 0];

        foreach ($names as $name) {
            $tags = $this->tagRepository->findAllSharingName($name);
            if (\count($tags) < 2) {
                continue;
            }

            $usage = $this->countUsage(array_map(static fn (Tag $tag): int => (int) $tag->getId(), $tags));
            $keeper = $this->chooseKeeper($tags, $usage);
            $losers = array_values(array_filter($tags, static fn (Tag $tag): bool => $tag !== $keeper));

            // Described before the merge: a deleted tag has no id left once flushed
            $line = \sprintf(
                ' keep #%d "%s" (%d events), remove %s',
                $keeper->getId(),
                $keeper->getName(),
                $this->usageOf($usage, $keeper),
                implode(', ', array_map(fn (Tag $tag): string => \sprintf('#%d "%s" (%d events)', $tag->getId(), $tag->getName(), $this->usageOf($usage, $tag)), $losers)),
            );

            $merged = $this->mergeGroup($keeper, $losers, $apply);
            $totals['tagsRemoved'] += \count($losers);
            $totals['categoriesMoved'] += $merged['categories'];
            $totals['themesMoved'] += $merged['themes'];
            $totals['eventsReindexed'] += $merged['reindexed'];

            $io->writeln($line);

            $this->entityManager->clear();
        }

        $io->newLine();
        $io->table(
            ['Names', 'Tags removed', 'Categories moved', 'Themes moved', 'Events reindexed'],
            [[\count($names), $totals['tagsRemoved'], $totals['categoriesMoved'], $totals['themesMoved'], $totals['eventsReindexed']]],
        );

        if ($apply) {
            $io->success('Merges written.');
        } else {
            $io->note('Preview only, nothing was written. Re-run with --apply to merge.');
        }

        return Command::SUCCESS;
    }

    /**
     * How many events use each tag, as category and as theme.
     *
     * @param int[] $tagIds
     *
     * @return array<int, array{category: int, themes: int}>
     */
    private function countUsage(array $tagIds): array
    {
        $connection = $this->entityManager->getConnection();
        $usage = array_fill_keys($tagIds, ['category' => 0, 'themes' => 0]);

        $sql = \sprintf('SELECT category_id AS tag_id, COUNT(*) AS nb FROM %s WHERE category_id IN (:ids) GROUP BY category_id', $connection->quoteSingleIdentifier('event'));
        foreach ($connection->fetchAllAssociative($sql, ['ids' => $tagIds], ['ids' => ArrayParameterType::INTEGER]) as $row) {
            $usage[(int) $row['tag_id']]['category'] = (int) $row['nb'];
        }

        $sql = 'SELECT tag_id, COUNT(*) AS nb FROM event_tag WHERE tag_id IN (:ids) GROUP BY tag_id';
        foreach ($connection->fetchAllAssociative($sql, ['ids' => $tagIds], ['ids' => ArrayParameterType::INTEGER]) as $row) {
            $usage[(int) $row['tag_id']]['themes'] = (int) $row['nb'];
        }

        return $usage;
    }

    /**
     * @param array<int, array{category: int, themes: int}> $usage
     */
    private function usageOf(array $usage, Tag $tag): int
    {
        $counts = $usage[$tag->getId()] ?? ['category' => 0, 'themes' => 0];

        return $counts['category'] + $counts['themes'];
    }

    /**
     * The most used tag; on a tie the oldest one, which the repository lists first.
     *
     * @param Tag[]                                         $tags
     * @param array<int, array{category: int, themes: int}> $usage
     */
    private function chooseKeeper(array $tags, array $usage): Tag
    {
        $keeper = $tags[0];
        foreach ($tags as $tag) {
            if ($this->usageOf($usage, $tag) > $this->usageOf($usage, $keeper)) {
                $keeper = $tag;
            }
        }

        return $keeper;
    }

    /**
     * Re-point every event of the losers to the keeper and delete them, in one
     * transaction per group. Search documents are queued once it is committed.
     *
     * @param Tag[] $losers
     *
     * @return array{categories: int, themes: int, reindexed: int}
     */
    private function mergeGroup(Tag $keeper, array $losers, bool $apply): array
    {
        $connection = $this->entityManager->getConnection();
        $merged = ['categories' => 0, 'themes' => 0, 'reindexed' => 0];

        if (!$apply) {
            $usage = $this->countUsage(array_map(static fn (Tag $tag): int => (int) $tag->getId(), $losers));
            foreach ($losers as $loser) {
                $merged['categories'] += $usage[$loser->getId()]['category'] ?? 0;
                $merged['themes'] += $usage[$loser->getId()]['themes'] ?? 0;
            }

            $merged['reindexed'] = $merged['categories'] + $merged['themes'];

            return $merged;
        }

        $connection->beginTransaction();
        $this->messageDispatcher->begin();

        try {
            $affectedEventIds = [];
            foreach ($losers as $loser) {
                $repointed = $this->repoint($connection, $keeper, $loser);
                $affectedEventIds = [...$affectedEventIds, ...$repointed['events']];
                $merged['categories'] += $repointed['categories'];
                $merged['themes'] += $repointed['themes'];
                $this->entityManager->remove($loser);
            }

            $this->entityManager->flush();
            $merged['reindexed'] = $this->scheduleReindex(array_values(array_unique($affectedEventIds)));

            $connection->commit();
        } catch (Throwable $e) {
            $this->messageDispatcher->rollBack();
            $connection->rollBack();

            throw $e;
        }

        $this->messageDispatcher->commit();

        return $merged;
    }

    /**
     * Set-based re-pointing: categories by a plain update, themes by copying the
     * loser's rows the keeper does not already have (event_tag is keyed on the pair)
     * and dropping the loser's.
     *
     * @return array{events: int[], categories: int, themes: int} ids of the events that changed, and how many categories and themes moved
     */
    private function repoint(Connection $connection, Tag $keeper, Tag $loser): array
    {
        $event = $connection->quoteSingleIdentifier('event');
        $parameters = ['keeper' => $keeper->getId(), 'loser' => $loser->getId()];

        $affected = [
            ...$connection->fetchFirstColumn(\sprintf('SELECT id FROM %s WHERE category_id = :loser', $event), $parameters),
            ...$connection->fetchFirstColumn('SELECT event_id FROM event_tag WHERE tag_id = :loser', $parameters),
        ];

        $categories = (int) $connection->executeStatement(\sprintf('UPDATE %s SET category_id = :keeper WHERE category_id = :loser', $event), $parameters);
        $themes = (int) $connection->executeStatement('INSERT INTO event_tag (event_id, tag_id) SELECT event_id, :keeper FROM event_tag WHERE tag_id = :loser AND event_id NOT IN (SELECT event_id FROM event_tag WHERE tag_id = :keeper)', $parameters);
        $connection->executeStatement('DELETE FROM event_tag WHERE tag_id = :loser', $parameters);

        return ['events' => array_map(intval(...), $affected), 'categories' => $categories, 'themes' => $themes];
    }

    /**
     * Queue a document refresh for the indexable events among the given ids. The
     * listener cannot see set-based updates, and the tag names are part of the document.
     *
     * @param int[] $eventIds
     */
    private function scheduleReindex(array $eventIds): int
    {
        $reindexed = 0;
        foreach (array_chunk($eventIds, self::REINDEX_CHUNK_SIZE) as $chunk) {
            /** @var int[] $indexable */
            $indexable = $this->entityManager
                ->createQuery('SELECT e.id FROM ' . Event::class . ' e WHERE e.id IN (:ids) AND e.draft = false AND e.duplicateOf IS NULL')
                ->setParameter('ids', $chunk)
                ->getSingleColumnResult();

            if ([] === $indexable) {
                continue;
            }

            $this->messageDispatcher->dispatch(new ReplaceManyDocuments('event', Event::class, $indexable));
            $reindexed += \count($indexable);
        }

        return $reindexed;
    }
}
