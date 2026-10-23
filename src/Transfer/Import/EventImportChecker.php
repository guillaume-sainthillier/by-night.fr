<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Entity\EventImport;
use App\Enum\EventImportStatus;
use App\Repository\EventImportRepository;
use App\SearchRepository\DuplicateEventFinder;
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\InvalidSheetException;
use App\Transfer\Sheet\SheetReader;
use App\Transfer\Sheet\SheetRow;
use App\Transfer\Sheet\SheetWriter;
use App\Transfer\TransferFiles;
use App\Transfer\TransferNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Checks a member's spreadsheet without writing any event: reads it, groups its rows into events, and judges each
 * one as the event form would. It leaves two files: the rows it kept, which the import reads (so what is imported is
 * exactly what was checked), and the rows it turned down with an "Erreurs" column, for the member to fix and import
 * again. The page follows it live, every few events.
 *
 * A member may cancel the check: it stops at its next step and writes nothing more.
 */
final readonly class EventImportChecker
{
    /** The events checked between two saves of the progress */
    private const int CHUNK = 25;

    public const string ERRORS_HEADER = 'Erreurs';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventImportRepository $importRepository,
        private TransferFiles $files,
        private SheetReader $reader,
        private SheetWriter $writer,
        private EventRowGrouper $grouper,
        private EventRowMapper $mapper,
        private DuplicateEventFinder $duplicateEventFinder,
        private UrlGeneratorInterface $urlGenerator,
        private TransferNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    public function check(EventImport $import): void
    {
        if (!$this->importRepository->transition($import, [EventImportStatus::Uploaded], EventImportStatus::Checking)) {
            // Cancelled meanwhile, or already checked by a former delivery of the message
            return;
        }

        $this->entityManager->refresh($import);
        $import->startCheck();
        $this->save($import);

        $local = null;
        $locals = [];
        try {
            $local = $this->files->download((string) $import->getFilePath(), $import->getFormat() ?? throw new InvalidSheetException('Format inconnu.'));
            $sheet = $this->reader->read($local, $import->getFormat());
            $groups = $this->grouper->group($sheet['rows']);
            $import->setRowCount(\count($sheet['rows']))->setEventCount(\count($groups));
            $this->save($import);

            $user = $import->getUser() ?? throw new InvalidSheetException('L’import n’a plus de membre.');
            $publisher = $import->getPublisher() ?? throw new InvalidSheetException('L’import n’a plus d’organisateur.');
            $kept = [];
            $turnedDown = [];
            $searchUp = true;
            foreach ($groups as $i => $group) {
                $mapped = $this->withDuplicateWarnings($this->mapper->map($group, $user, $publisher), $searchUp);
                $import->addCheckedEvent(
                    array_map(static fn (RowProblem $problem): array => $problem->toArray(), $mapped->errors),
                    array_map(static fn (RowProblem $problem): array => $problem->toArray(), $mapped->warnings),
                );
                if ($mapped->isValid()) {
                    array_push($kept, ...$group->rows);
                } else {
                    foreach ($group->rows as $row) {
                        $turnedDown[] = [$row, self::rowErrors($row, $mapped->errors)];
                    }
                }

                if (0 === ($i + 1) % self::CHUNK) {
                    if ($this->isCancelled($import)) {
                        return;
                    }

                    $this->save($import);
                }
            }

            if ($this->isCancelled($import)) {
                return;
            }

            $import->setSuccessFilePath($this->writeRows($import, 'valides', $sheet['headers'], self::sorted($kept), $locals));
            $import->setErrorFilePath([] === $turnedDown ? null : $this->writeErrors($import, $sheet['headers'], $turnedDown, $locals));
            $import->setStatus(EventImportStatus::Checked);
        } catch (InvalidSheetException $e) {
            $import->setStatus(EventImportStatus::CheckFailed)->setFailureReason($e->getMessage());
        } catch (Throwable $e) {
            $this->logger->error('The check of the event import {id} failed: {message}', ['id' => $import->getId(), 'message' => $e->getMessage(), 'exception' => $e]);
            $import->setStatus(EventImportStatus::CheckFailed)->setFailureReason('Une erreur inattendue a interrompu la vérification. Réessayez plus tard, ou écrivez-nous si elle se répète.');
        } finally {
            $this->files->remove(...array_filter([$local, ...$locals]));
        }

        $this->save($import);
    }

    /**
     * A new event that looks like one already online: worth a look, not a reason to turn it down (it may be the
     * member's own, imported by By Night from another source).
     *
     * @param bool $searchUp false once the search failed: its client retries for a minute or so each time, the rest
     *                       of the check goes without the hint
     */
    private function withDuplicateWarnings(MappedEvent $mapped, bool &$searchUp): MappedEvent
    {
        if (!$searchUp || !$mapped->isValid() || null !== $mapped->eventId) {
            return $mapped;
        }

        try {
            $duplicates = $this->duplicateEventFinder->find($mapped->dto, 1);
        } catch (Throwable $e) {
            // Search being down only costs the hint
            $this->logger->warning('Duplicates could not be looked for: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $searchUp = false;

            return $mapped;
        }

        foreach ($duplicates as $duplicate) {
            $url = $this->urlGenerator->generate('app_event_details', ['slug' => $duplicate->getSlug(), 'id' => $duplicate->getId(), 'location' => $duplicate->getLocationSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
            $mapped = $mapped->withWarnings(RowProblem::at($mapped->group->first(), EventColumn::Name, \sprintf('« %s » a déjà lieu aux mêmes dates au même endroit sur By Night : vérifiez que ce n’est pas le même événement.', $duplicate->getName()))->withUrl($url));
        }

        return $mapped;
    }

    private function isCancelled(EventImport $import): bool
    {
        return EventImportStatus::Cancelled === $this->importRepository->findStatus($import);
    }

    private function save(EventImport $import): void
    {
        $this->entityManager->flush();
        $this->notifier->importChanged($import);
    }

    /**
     * @param list<RowProblem> $errors
     */
    private static function rowErrors(SheetRow $row, array $errors): string
    {
        $own = array_filter($errors, static fn (RowProblem $problem): bool => $problem->row === $row->number);

        return [] === $own
            ? 'Une autre ligne de cet événement est en erreur.'
            : implode("\n", array_map(static fn (RowProblem $problem): string => $problem->toCell(), $own));
    }

    /**
     * @param list<SheetRow> $rows
     *
     * @return list<SheetRow> in the file's order
     */
    private static function sorted(array $rows): array
    {
        usort($rows, static fn (SheetRow $a, SheetRow $b): int => $a->number <=> $b->number);

        return $rows;
    }

    /**
     * @param list<string>   $headers
     * @param list<SheetRow> $rows
     * @param list<string>   $locals  the local files to remove
     *
     * @return string the stored file's path
     */
    private function writeRows(EventImport $import, string $name, array $headers, array $rows, array &$locals): string
    {
        $columns = self::reportColumns($headers);

        return $this->store($import, $name, self::pick($headers, $columns), array_map(static fn (SheetRow $row): array => self::pick($row->cells, $columns), $rows), $locals);
    }

    /**
     * @param list<string>                        $headers
     * @param list<array{0: SheetRow, 1: string}> $rows
     * @param list<string>                        $locals
     */
    private function writeErrors(EventImport $import, array $headers, array $rows, array &$locals): string
    {
        usort($rows, static fn (array $a, array $b): int => $a[0]->number <=> $b[0]->number);
        $columns = self::reportColumns($headers);

        return $this->store($import, 'erreurs', [...self::pick($headers, $columns), self::ERRORS_HEADER], array_map(static fn (array $row): array => [...self::pick($row[0]->cells, $columns), $row[1]], $rows), $locals);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     * @param list<string>       $locals
     */
    private function store(EventImport $import, string $name, array $headers, array $rows, array &$locals): string
    {
        $format = $import->getFormat() ?? throw new InvalidSheetException('Format inconnu.');
        $locals[] = $local = $this->files->createLocalFile($format);
        $this->writer->write($local, $format, $headers, $rows);
        // Next to the original: the import's files share one directory, removed with it
        $path = \sprintf('%s/%s.%s', \dirname((string) $import->getFilePath()), $name, $format->value);
        $this->files->upload($local, $path);

        return $path;
    }

    /**
     * The columns the reports copy: all of the file's, but the "Erreurs" of an error file imported again, which the
     * new error file writes afresh.
     *
     * @param list<string> $headers
     *
     * @return list<int>
     */
    private static function reportColumns(array $headers): array
    {
        return array_keys(array_filter($headers, static fn (string $header): bool => 0 !== strcasecmp(trim($header), self::ERRORS_HEADER)));
    }

    /**
     * @param list<string> $cells
     * @param list<int>    $columns
     *
     * @return list<string>
     */
    private static function pick(array $cells, array $columns): array
    {
        return array_map(static fn (int $column): string => $cells[$column] ?? '', $columns);
    }
}
