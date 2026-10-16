<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Export;

use App\Entity\Event;
use App\Entity\EventExport;
use App\Enum\EventExportStatus;
use App\Enum\TransferFormat;
use App\Repository\EventExportRepository;
use App\Repository\EventRepository;
use App\Transfer\Sheet\SheetWriter;
use App\Transfer\TransferFiles;
use App\Transfer\TransferNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Generator;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes a member's export: their events of its scope, page by page, as a spreadsheet the import reads back, or as
 * a calendar. The page follows its progress live.
 */
final readonly class EventExporter
{
    private const int PAGE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventExportRepository $exportRepository,
        private EventRepository $eventRepository,
        private EventRowFormatter $formatter,
        private SheetWriter $sheetWriter,
        private EventCalendarWriter $calendarWriter,
        private TransferFiles $files,
        private TransferNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    public function export(EventExport $export): void
    {
        if (!$this->exportRepository->transition($export, [EventExportStatus::Pending], EventExportStatus::InProgress)) {
            return;
        }

        $this->entityManager->refresh($export);
        $id = (int) $export->getId();
        $user = $export->getUser() ?? throw new LogicException('The export has no member.');
        $format = $export->getFormat() ?? throw new LogicException('The export has no format.');
        $qb = $this->eventRepository->createExportQueryBuilder($user, $export->getScope(), $export->getSearchQuery(), $export->getStatusFilter());
        $export->setTotalCount((int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult())->setExportedCount(0);
        $this->save($export);

        $local = $this->files->createLocalFile($format);
        try {
            $events = $this->events($id, $qb);
            if (TransferFormat::Ics === $format) {
                $this->calendarWriter->write($local, $events);
            } else {
                $this->sheetWriter->write($local, $format, EventRowFormatter::headers(), $this->rows($events));
            }

            $path = \sprintf('exports/%s/evenements.%s', bin2hex(random_bytes(8)), $format->value);
            $this->files->upload($local, $path);
            $export = $this->find($id);
            $export->setFilePath($path)->setStatus(EventExportStatus::Completed);
        } catch (Throwable $e) {
            $this->logger->error('The event export {id} failed: {message}', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            $export = $this->find($id);
            $export->setStatus(EventExportStatus::Failed)->setFailureReason('L’export s’est interrompu. Relancez-le, ou écrivez-nous si l’erreur se répète.');
        } finally {
            $this->files->remove($local);
        }

        $this->save($export);
    }

    /**
     * The export's events, a page at a time: each page counted on the export, then let go of.
     *
     * @return Generator<Event>
     */
    private function events(int $exportId, QueryBuilder $qb): Generator
    {
        $after = 0;
        $exported = 0;
        do {
            $page = $this->eventRepository->findExportPage($qb, $after, self::PAGE);
            foreach ($page as $event) {
                yield $event;
                $after = (int) $event->getId();
            }

            $exported += \count($page);
            $this->entityManager->clear();
            $export = $this->find($exportId);
            $export->setExportedCount($exported);
            $this->save($export);
        } while (self::PAGE === \count($page));
    }

    /**
     * @param iterable<Event> $events
     *
     * @return Generator<list<string>>
     */
    private function rows(iterable $events): Generator
    {
        foreach ($events as $event) {
            yield from $this->formatter->rows($event);
        }
    }

    private function find(int $id): EventExport
    {
        return $this->exportRepository->find($id) ?? throw new LogicException(\sprintf('The export %d is gone.', $id));
    }

    private function save(EventExport $export): void
    {
        $this->entityManager->flush();
        $this->notifier->exportChanged($export);
    }
}
