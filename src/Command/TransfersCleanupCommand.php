<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Command;

use App\Enum\EventExportStatus;
use App\Enum\EventImportStatus;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Transfer\TransferFiles;
use App\Transfer\TransferNotifier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The daily round of the members' imports and exports (host cron):
 * - a job a worker should be on but has not moved for a while lost its worker (a deploy, a crash): it is failed,
 *   which tells its member, and an import can then be resumed;
 * - the jobs older than the retention are deleted with their files, which hold the members' contacts.
 */
#[AsCommand('app:transfers:cleanup', 'Fail the stuck event imports and exports, delete the old ones with their files')]
final readonly class TransfersCleanupCommand
{
    public function __construct(
        private EventImportRepository $importRepository,
        private EventExportRepository $exportRepository,
        private TransferFiles $files,
        private TransferNotifier $notifier,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('A job that has not moved for this long lost its worker (a date modifier)')]
        string $stuckAfter = '2 hours',
        #[Option('How long the jobs and their files are kept (a date modifier)')]
        string $keep = '30 days',
    ): int {
        $stuck = 0;
        foreach ($this->importRepository->findStuckSince(new DateTimeImmutable('-' . $stuckAfter)) as $import) {
            $checking = \in_array($import->getStatus(), [EventImportStatus::Uploaded, EventImportStatus::Checking], true);
            $import
                ->setStatus($checking ? EventImportStatus::CheckFailed : EventImportStatus::Failed)
                ->setFailureReason($checking
                    ? 'La vérification ne s’est pas terminée : envoyez le fichier à nouveau.'
                    : 'L’import s’est interrompu. Les événements déjà importés sont enregistrés : reprenez l’import pour terminer les autres.');
            $this->entityManager->flush();
            $this->notifier->importChanged($import);
            ++$stuck;
        }

        foreach ($this->exportRepository->findStuckSince(new DateTimeImmutable('-' . $stuckAfter)) as $export) {
            $export->setStatus(EventExportStatus::Failed)->setFailureReason('L’export ne s’est pas terminé : relancez-le.');
            $this->entityManager->flush();
            $this->notifier->exportChanged($export);
            ++$stuck;
        }

        $deleted = 0;
        $before = new DateTimeImmutable('-' . $keep);
        foreach ([...$this->importRepository->findExpiredBefore($before), ...$this->exportRepository->findExpiredBefore($before)] as $job) {
            $this->files->deleteDirectoryOf($job->getFilePath());
            $this->entityManager->remove($job);
            ++$deleted;
        }

        $this->entityManager->flush();
        $io->success(\sprintf('%d stuck job(s) failed, %d old job(s) deleted with their files.', $stuck, $deleted));

        return Command::SUCCESS;
    }
}
