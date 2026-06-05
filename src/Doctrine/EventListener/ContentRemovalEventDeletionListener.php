<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Enum\ContentRemovalRequestStatus;
use App\Manager\MailerManager;
use App\Repository\ContentRemovalRequestRepository;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles content removal requests linked to an event when that event is
 * deleted, regardless of how the deletion is triggered (admin action, batch
 * action or import cleanup).
 *
 * During onFlush — while the many-to-many join rows still exist — the linked
 * requests are collected and any still-pending one is marked as processed in
 * the same transaction as the deletion. The requesters are then notified during
 * postFlush, once the deletion has been committed.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ContentRemovalEventDeletionListener
{
    /** @var array<int, ContentRemovalRequest> */
    private array $queue = [];

    public function __construct(
        private readonly ContentRemovalRequestRepository $contentRemovalRequestRepository,
        private readonly MailerManager $mailerManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $deletedEvents = array_filter(
            $unitOfWork->getScheduledEntityDeletions(),
            static fn (object $entity): bool => $entity instanceof Event,
        );

        if ([] === $deletedEvents) {
            return;
        }

        $metadata = $entityManager->getClassMetadata(ContentRemovalRequest::class);

        foreach ($deletedEvents as $event) {
            foreach ($this->contentRemovalRequestRepository->findByEvent($event) as $contentRemovalRequest) {
                // Deduplicate when a request is linked to several events removed in the same flush.
                $this->queue[$contentRemovalRequest->getId()] = $contentRemovalRequest;

                // A pending request whose event is being purged outside of the admin workflow
                // is considered handled: close it in the same transaction as the deletion.
                if (ContentRemovalRequestStatus::Pending === $contentRemovalRequest->getStatus()) {
                    $contentRemovalRequest->setStatus(ContentRemovalRequestStatus::Processed);
                    $contentRemovalRequest->setProcessedAt(new DateTimeImmutable());
                    $unitOfWork->computeChangeSet($metadata, $contentRemovalRequest);
                }
            }
        }
    }

    public function postFlush(): void
    {
        if ([] === $this->queue) {
            return;
        }

        $queue = $this->queue;
        $this->queue = [];

        foreach ($queue as $contentRemovalRequest) {
            try {
                $this->mailerManager->sendContentRemovalEventDeletedEmail($contentRemovalRequest);
            } catch (Throwable $throwable) {
                // A mail failure must not break the event deletion (e.g. bulk import cleanup).
                $this->logger->error('Failed to notify requester of content removal event deletion.', [
                    'contentRemovalRequest' => $contentRemovalRequest->getId(),
                    'exception' => $throwable,
                ]);
            }
        }
    }
}
