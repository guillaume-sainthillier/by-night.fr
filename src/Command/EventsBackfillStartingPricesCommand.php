<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Entity\Event;
use App\Utils\StartingPrice;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Index;
use Elastica\Mapping;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\MappingBuilder;
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
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * One-off fill of Event::$startingPrice for the events stored before it existed: an event imported or edited since
 * gets it from Event::setPrices(). Reads the prices of every event by blocks of ids (CursorPagination), writes the changed ones by DQL bulk
 * updates (no lifecycle callback, no updatedAt), then re-indexes the ones to come through RefreshEventDocuments. Run it
 * again after a change of StartingPrice's rules: it only writes what changed.
 */
#[AsCommand(
    name: 'app:events:backfill-starting-prices',
    description: 'Store the lowest price of every event, read from its prices (preview with --dry-run)',
)]
final class EventsBackfillStartingPricesCommand extends Command
{
    private const int READ_SIZE = 10_000;

    private const int BATCH_SIZE = 1_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        #[Autowire(service: 'fos_elastica.index.event')]
        private readonly Index $eventIndex,
        #[Autowire(service: 'fos_elastica.config_manager')]
        private readonly ConfigManager $configManager,
        #[Autowire(service: 'fos_elastica.mapping_builder')]
        private readonly MappingBuilder $mappingBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count what would change without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $today = new DateTimeImmutable('today');

        if (!$dryRun) {
            $this->addMapping();
        }

        /** @var array<string, list<int>> $changes the ids of the events to write, by their new price ('' for none) */
        $changes = [];
        /** @var list<int> $toCome the ids of the changed events that end from today on, which the index holds */
        $toCome = [];
        $read = 0;
        $io->progressStart();

        foreach ($this->createPagination($today)->getChunkResults() as $rows) {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $stored = null === $row['startingPrice'] ? null : (float) $row['startingPrice'];
                $startingPrice = StartingPrice::fromPrices($row['prices']);
                if ($startingPrice === $stored) {
                    continue;
                }

                $changes[(string) $startingPrice][] = $id;
                if (1 === (int) $row['toCome']) {
                    $toCome[] = $id;
                }
            }

            $read += \count($rows);
            $io->progressAdvance(\count($rows));
        }

        $io->progressFinish();
        $written = array_sum(array_map(\count(...), $changes));

        if ($dryRun) {
            $io->note(\sprintf('%d of %d events would change, %d of them to come. Nothing was written.', $written, $read, \count($toCome)));

            return Command::SUCCESS;
        }

        foreach ($changes as $startingPrice => $ids) {
            foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                $this
                    ->entityManager
                    ->createQueryBuilder()
                    ->update(Event::class, 'e')
                    ->set('e.startingPrice', ':startingPrice')
                    ->where('e.id IN (:ids)')
                    ->setParameter('startingPrice', '' === $startingPrice ? null : (float) $startingPrice)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute();
            }
        }

        foreach (array_chunk($toCome, self::BATCH_SIZE) as $chunk) {
            $this->messageBus->dispatch(new RefreshEventDocuments(eventIds: $chunk));
        }

        $io->success(\sprintf('Starting price of %d events updated, %d of them to come re-indexed by the async worker.', $written, \count($toCome)));

        return Command::SUCCESS;
    }

    /**
     * The prices of every event, by blocks walked by id: scalar rows, no entity to hydrate nor clear.
     *
     * @return CursorPagination<array{id: int|string, prices: string|null, startingPrice: float|string|null, toCome: int|string}>
     */
    private function createPagination(DateTimeImmutable $today): CursorPagination
    {
        $queryBuilder = $this
            ->entityManager
            ->createQueryBuilder()
            ->select('e.id, e.prices, e.startingPrice')
            // Whether the index holds it, which only the events to come are in
            ->addSelect('CASE WHEN e.endDate >= :today THEN 1 ELSE 0 END AS toCome')
            ->from(Event::class, 'e')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE);

        return new CursorPagination(
            $queryBuilder,
            new OrderConfigurations(new OrderConfiguration('e.id', static fn (array $row): int => (int) $row['id'])),
            self::READ_SIZE,
            fetchJoinCollection: false,
        );
    }

    /**
     * An index created before the field has no mapping for it, and the first document written would map it from its
     * value: a long for a whole price ("22", json_encode() drops the ".0"), which would cut the cents of the next ones.
     * The configured mapping of the field is added first: run the command right after the migration, before the
     * imports write the field.
     */
    private function addMapping(): void
    {
        $mapping = $this->mappingBuilder->buildMapping(null, $this->configManager->getIndexConfiguration('event'));

        $this->eventIndex->setMapping(new Mapping(['startingPrice' => $mapping['properties']['startingPrice']]));
    }
}
