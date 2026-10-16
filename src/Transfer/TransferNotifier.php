<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer;

use App\Entity\EventExport;
use App\Entity\EventImport;
use App\Entity\User;
use App\Mercure\UserChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Tells the member's pages where their import or export stands, live: its whole state on their channel (Mercure),
 * under the job's own IRI. A page that missed an update catches up with the next one, or reads the state again
 * when it reconnects (the job's JSON route).
 *
 * The hub being down only costs the live progress: the job goes on, its page shows it on the next load.
 */
final readonly class TransferNotifier
{
    public const string IMPORT_EVENT = 'event-import';
    public const string EXPORT_EVENT = 'event-export';

    public function __construct(
        private HubInterface $hub,
        private UserChannel $userChannel,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function importChanged(EventImport $import): void
    {
        $iri = $this->urlGenerator->generate('app_event_import_show', ['id' => $import->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->publish($import->getUser(), $iri, self::IMPORT_EVENT, self::importState($import));
    }

    public function exportChanged(EventExport $export): void
    {
        $iri = $this->urlGenerator->generate('app_event_export_show', ['id' => $export->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->publish($export->getUser(), $iri, self::EXPORT_EVENT, self::exportState($export));
    }

    /**
     * @return array<string, mixed>
     */
    public static function importState(EventImport $import): array
    {
        return [
            'id' => $import->getId(),
            'status' => $import->getStatus()->value,
            'statusLabel' => $import->getStatus()->getLabel(),
            'running' => $import->getStatus()->isRunning(),
            'events' => $import->getEventCount(),
            'processed' => $import->getProcessedCount(),
            'percent' => 0 === $import->getEventCount() ? 0 : (int) floor(100 * $import->getProcessedCount() / $import->getEventCount()),
            'valid' => $import->getValidCount(),
            'invalid' => $import->getInvalidCount(),
            'warnings' => $import->getWarningCount(),
            'created' => $import->getCreatedCount(),
            'updated' => $import->getUpdatedCount(),
            'unchanged' => $import->getUnchangedCount(),
            'failed' => $import->getFailedCount(),
            'skipped' => $import->getSkippedCount(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exportState(EventExport $export): array
    {
        return [
            'id' => $export->getId(),
            'status' => $export->getStatus()->value,
            'statusLabel' => $export->getStatus()->getLabel(),
            'running' => $export->getStatus()->isRunning(),
            'total' => $export->getTotalCount(),
            'exported' => $export->getExportedCount(),
            'percent' => 0 === $export->getTotalCount() ? 0 : (int) floor(100 * $export->getExportedCount() / $export->getTotalCount()),
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function publish(?User $user, string $iri, string $type, array $state): void
    {
        if (null === $user) {
            return;
        }

        try {
            $this->hub->publish($this->userChannel->update($user, $iri, $type, $state));
        } catch (Throwable $e) {
            $this->logger->warning('The progress of {iri} could not be published: {message}', ['iri' => $iri, 'message' => $e->getMessage(), 'exception' => $e]);
        }
    }
}
