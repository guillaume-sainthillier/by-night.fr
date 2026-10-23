<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Publisher;
use App\Entity\PublisherLegacySlug;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Repository\EventRepository;
use App\Repository\PublisherLegacySlugRepository;
use App\Transfer\TransferFiles;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * Deletes a publisher its members no longer want, in one transaction. Its events go to another publisher of its owner
 * (the heir), whose page its URL then leads to, or are deleted with it; its imports and exports go with their files;
 * its logo and cover with it (VichUploader). Never while one of its jobs runs: the worker would lose it halfway.
 *
 * The publisher's event pages are purged with the flush that moves or deletes them (EventPageCachePurgeListener).
 */
final readonly class PublisherRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private EventImportRepository $importRepository,
        private EventExportRepository $exportRepository,
        private PublisherLegacySlugRepository $legacySlugRepository,
        private TransferFiles $files,
    ) {
    }

    /**
     * Whether an import or an export of the publisher is running: it may not be deleted until it is done.
     */
    public function hasJobRunning(Publisher $publisher): bool
    {
        return 0 < $this->importRepository->countRunningByPublisher($publisher) + $this->exportRepository->countRunningByPublisher($publisher);
    }

    public function remove(Publisher $publisher, ?Publisher $heir): void
    {
        if (null !== $heir && ($heir->getId() === $publisher->getId() || $heir->getOwner()?->getId() !== $publisher->getOwner()?->getId())) {
            throw new LogicException('The events go to another publisher of the same owner.');
        }

        if ($this->hasJobRunning($publisher)) {
            throw new LogicException('A job of the publisher is running.');
        }

        $jobFiles = [];
        $this->entityManager->wrapInTransaction(function () use ($publisher, $heir, &$jobFiles): void {
            $events = $this->eventRepository->findBy(['publisher' => $publisher]);
            if (null === $heir) {
                foreach ($events as $event) {
                    $this->entityManager->remove($event);
                }
            } else {
                // A reference is unique among a publisher's events (Event::$externalReference): the heir keeps its own
                $references = array_fill_keys(array_map(mb_strtolower(...), $this->eventRepository->findReferencesOf($heir)), true);
                foreach ($events as $event) {
                    $reference = null === $event->getExternalReference() ? null : mb_strtolower($event->getExternalReference());
                    if (null !== $reference && isset($references[$reference])) {
                        $event->setExternalReference(null);
                    } elseif (null !== $reference) {
                        $references[$reference] = true;
                    }

                    $event->setPublisher($heir);
                }

                // Its URL and those that already led to it lead to the heir
                $this->legacySlugRepository->moveTo($publisher, $heir);
                $legacySlug = $this->legacySlugRepository->findOneBy(['slug' => $publisher->getSlug()]);
                if (null === $legacySlug) {
                    $this->entityManager->persist(new PublisherLegacySlug($heir, (string) $publisher->getSlug()));
                } else {
                    $legacySlug->setPublisher($heir);
                }
            }

            foreach ([...$this->importRepository->findBy(['publisher' => $publisher]), ...$this->exportRepository->findBy(['publisher' => $publisher])] as $job) {
                $jobFiles[] = $job->getFilePath();
                $this->entityManager->remove($job);
            }

            $this->entityManager->remove($publisher);
            $this->entityManager->flush();
        });

        // Once the rows are gone only: a failed deletion keeps its files
        foreach ($jobFiles as $path) {
            $this->files->deleteDirectoryOf($path);
        }
    }
}
