<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Dto\EventDto;
use App\Entity\EventImport;
use App\Entity\User;
use App\Enum\EventImportStatus;
use App\Manager\MemberEventPublisher;
use App\Repository\EventImportRepository;
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\InvalidSheetException;
use App\Transfer\Sheet\SheetReader;
use App\Transfer\TransferFiles;
use App\Transfer\TransferNotifier;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Imports the events a check kept: it reads the check's file of valid rows, not the original, so what is imported
 * is exactly what the member saw checked. Each event is read again against the member's events as they are now
 * (one may have been deleted meanwhile), then saved in batches through the import pipeline.
 *
 * The events of a batch are saved together: a batch the pipeline cannot save is saved again event by event, so
 * one bad event fails alone. Run again (a worker killed halfway, the member resuming), the import matches the
 * events it already wrote by their ID or reference, and they come out unchanged.
 */
final readonly class EventImportRunner
{
    private const int CHUNK = 25;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventImportRepository $importRepository,
        private TransferFiles $files,
        private SheetReader $reader,
        private EventRowGrouper $grouper,
        private EventRowMapper $mapper,
        private MemberEventPublisher $memberEventPublisher,
        private TransferNotifier $notifier,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Imports an import its member started (Importing, see ImportController::run()).
     */
    public function run(EventImport $import): void
    {
        $this->entityManager->refresh($import);
        if (EventImportStatus::Importing !== $import->getStatus()) {
            return;
        }

        $id = (int) $import->getId();
        $user = $import->getUser() ?? throw new LogicException('The import has no member.');
        $import->startImport();
        $this->save($import);

        $outcomes = [];
        $local = null;
        try {
            $format = $import->getFormat() ?? throw new InvalidSheetException('Format inconnu.');
            $local = $this->files->download($import->getSuccessFilePath() ?? throw new InvalidSheetException('Le fichier vérifié manque : vérifiez le fichier à nouveau.'), $format);
            $groups = $this->grouper->group($this->reader->read($local, $format)['rows']);

            // The events the member chose not to import (warned of on the check) come out skipped
            $skipped = array_flip($import->getSkippedRows());
            foreach (array_chunk($groups, self::CHUNK) as $chunk) {
                $chunkOutcomes = [];
                foreach ($chunk as $i => $group) {
                    if (isset($skipped[$group->first()->number])) {
                        $chunkOutcomes[] = ['row' => $group->first()->number, 'reference' => $group->reference(), 'id' => null, 'name' => $group->first()->get(EventColumn::Name), 'outcome' => 'skipped'];
                        unset($chunk[$i]);
                    }
                }

                // In the file's order, the skipped ones among the others
                $chunkOutcomes = [...$chunkOutcomes, ...$this->importChunk(array_values($chunk), $user)];
                usort($chunkOutcomes, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);
                array_push($outcomes, ...$chunkOutcomes);
                // Saving the events cleared the entity manager: the import is read again, its outcomes written over
                $import = $this->progress($id, $outcomes);
                $this->save($import);
            }

            $import->setStatus(EventImportStatus::Completed);
        } catch (Throwable $e) {
            $this->logger->error('The event import {id} failed: {message}', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            $import = $this->progress($id, $outcomes);
            $import->setStatus(EventImportStatus::Failed)->setFailureReason($e instanceof InvalidSheetException
                ? $e->getMessage()
                : 'L’import s’est interrompu. Les événements déjà importés sont enregistrés : reprenez l’import pour terminer les autres.');
        } finally {
            if (null !== $local) {
                $this->files->remove($local);
            }
        }

        $this->save($import);
    }

    /**
     * @param list<EventRowGroup> $groups
     *
     * @return list<array{row: int, reference: ?string, id: ?int, name: string, outcome: string}>
     */
    private function importChunk(array $groups, User $user): array
    {
        $outcomes = [];
        $toSave = [];
        foreach ($groups as $group) {
            $mapped = $this->mapper->map($group, $user);
            if (!$mapped->isValid()) {
                $outcomes[] = self::outcome($mapped, null, 'failed');
            } elseif (!$mapped->changed && null !== $mapped->eventId) {
                $outcomes[] = self::outcome($mapped, $mapped->eventId, 'unchanged');
            } else {
                $toSave[] = $mapped;
            }
        }

        if ([] === $toSave) {
            return $outcomes;
        }

        try {
            $ids = $this->memberEventPublisher->saveImported($user, array_map(self::item(...), $toSave));
        } catch (Throwable $e) {
            $this->logger->warning('A batch of the event import failed, its events are saved one by one: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $ids = [];
            foreach ($toSave as $mapped) {
                try {
                    $ids[] = $this->memberEventPublisher->saveImported($user, [self::item($this->mapper->map($mapped->group, $user))])[0];
                } catch (Throwable $e) {
                    $this->logger->error('An event of an import could not be saved: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                    $ids[] = null;
                }
            }
        }

        foreach ($toSave as $i => $mapped) {
            $outcomes[] = self::outcome($mapped, $ids[$i], null === $ids[$i] ? 'failed' : (null === $mapped->eventId ? 'created' : 'updated'));
        }

        return $outcomes;
    }

    /**
     * @return array{dto: EventDto, new: bool, draft: ?bool, reference: ?string}
     */
    private static function item(MappedEvent $mapped): array
    {
        return ['dto' => $mapped->dto, 'new' => null === $mapped->eventId, 'draft' => $mapped->draft, 'reference' => $mapped->reference];
    }

    /**
     * @return array{row: int, reference: ?string, id: ?int, name: string, outcome: string}
     */
    private static function outcome(MappedEvent $mapped, ?int $id, string $outcome): array
    {
        return [
            'row' => $mapped->group->first()->number,
            'reference' => $mapped->reference,
            'id' => $id,
            'name' => (string) ($mapped->dto->name ?? $mapped->group->first()->get(EventColumn::Name)),
            'outcome' => $outcome,
        ];
    }

    /**
     * The import as saved, with the outcomes so far.
     *
     * @param list<array{row: int, reference: ?string, id: ?int, name: string, outcome: string}> $outcomes
     */
    private function progress(int $id, array $outcomes): EventImport
    {
        $import = $this->importRepository->find($id) ?? throw new LogicException(\sprintf('The import %d is gone.', $id));
        $import->startImport();
        foreach ($outcomes as $outcome) {
            $import->addOutcome($outcome);
        }

        return $import;
    }

    private function save(EventImport $import): void
    {
        $this->entityManager->flush();
        $this->notifier->importChanged($import);
    }
}
