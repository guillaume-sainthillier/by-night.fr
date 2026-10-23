<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\EventExportRepository;
use App\Repository\EventImportRepository;
use App\Repository\EventRepository;
use App\Repository\PublisherRepository;
use App\Repository\UserEventRepository;
use App\Transfer\TransferFiles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Deletes a member's account, whether they close it from their profile or an admin deletes it: their
 * comments and favourites go with it (the counters of the favourite events are recounted), their events are
 * deleted too or stay online without an author. Comments, favourites and events reference the member
 * without any ON DELETE rule, so removing the account alone is refused by the database.
 *
 * All of it or nothing, in one transaction: the account is deleted in the same flush as what references it
 * (Doctrine deletes the rows referencing a member before the member), and the counters are written once it is flushed.
 * Their imports and exports go too, and their files once it is committed: nothing of the member stays in storage.
 */
final readonly class UserRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private UserEventRepository $userEventRepository,
        private CommentRepository $commentRepository,
        private PublisherRepository $publisherRepository,
        private EventImportRepository $importRepository,
        private EventExportRepository $exportRepository,
        // Lazy: the back office builds this service while loading its routes, before any S3 client may be configured
        #[Autowire(lazy: true)]
        private TransferFiles $files,
    ) {
    }

    public function remove(User $user, bool $withEvents): void
    {
        $jobFiles = [];
        $this->entityManager->wrapInTransaction(function () use ($user, $withEvents, &$jobFiles): void {
            // Before their publishers, whose jobs they are: the database would delete their rows, not their files
            foreach ([...$this->importRepository->findBy(['user' => $user]), ...$this->exportRepository->findBy(['user' => $user])] as $job) {
                $jobFiles[] = $job->getFilePath();
                $this->entityManager->remove($job);
            }

            foreach ($this->eventRepository->findBy(['user' => $user]) as $event) {
                if ($withEvents) {
                    $this->entityManager->remove($event);
                } else {
                    $event->setUser(null);
                    $event->setPublisher(null);
                }
            }

            // Its events gone or kept anonymous above
            foreach ($this->publisherRepository->findBy(['owner' => $user]) as $publisher) {
                $this->entityManager->remove($publisher);
            }

            // Loaded with their events, which the recount writes to (UserEventCountListener): one query, not one per
            // favourite
            foreach ($this->userEventRepository->findByUserWithEvents($user) as $userEvent) {
                $this->entityManager->remove($userEvent);
            }

            foreach ($this->commentRepository->findAllByUser($user) as $comment) {
                $this->entityManager->remove($comment);
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });

        // Once the rows are gone only: a failed deletion keeps its files
        foreach ($jobFiles as $path) {
            $this->files->deleteDirectoryOf($path);
        }
    }
}
