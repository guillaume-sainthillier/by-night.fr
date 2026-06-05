<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ContentRemovalRequestStatus;
use App\Manager\MailerManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/_administration/content-removal-action')]
final class ContentRemovalActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerManager $mailerManager,
    ) {
    }

    #[Route(path: '/{id}/remove-events', name: 'admin_content_removal_action_remove_events', methods: ['GET'])]
    public function removeEvents(ContentRemovalRequest $contentRemovalRequest): Response
    {
        $this->ensurePending($contentRemovalRequest);

        foreach ($contentRemovalRequest->getEvents() as $event) {
            $this->entityManager->remove($event);
        }

        // The requester is notified by ContentRemovalEventDeletionListener once the events are deleted.
        $this->markAsProcessed($contentRemovalRequest);
        $this->entityManager->flush();

        $this->addFlash('success', 'Les événements ont été supprimés.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[Route(path: '/{id}/remove-images', name: 'admin_content_removal_action_remove_images', methods: ['GET'])]
    public function removeImages(ContentRemovalRequest $contentRemovalRequest): Response
    {
        $this->ensurePending($contentRemovalRequest);

        foreach ($contentRemovalRequest->getEvents() as $event) {
            $this->removeEventImage($event);
        }

        $this->markAsProcessed($contentRemovalRequest);
        $this->entityManager->flush();

        $this->mailerManager->sendContentRemovalProcessedEmail($contentRemovalRequest);

        $this->addFlash('success', 'Les images des événements ont été supprimées.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[Route(path: '/{id}/mark-processed', name: 'admin_content_removal_action_mark_processed', methods: ['GET'])]
    public function markProcessed(ContentRemovalRequest $contentRemovalRequest): Response
    {
        $this->ensurePending($contentRemovalRequest);

        $this->markAsProcessed($contentRemovalRequest);
        $this->entityManager->flush();

        $this->mailerManager->sendContentRemovalProcessedEmail($contentRemovalRequest);

        $this->addFlash('success', 'La demande a été marquée comme traitée.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    #[Route(path: '/{id}/reject', name: 'admin_content_removal_action_reject', methods: ['GET'])]
    public function reject(ContentRemovalRequest $contentRemovalRequest): Response
    {
        $this->ensurePending($contentRemovalRequest);

        $this->markAsRejected($contentRemovalRequest);
        $this->entityManager->flush();

        $this->mailerManager->sendContentRemovalRejectedEmail($contentRemovalRequest);

        $this->addFlash('success', 'La demande a été rejetée.');

        return $this->redirectToRoute('admin_content_removal_request_index');
    }

    private function ensurePending(ContentRemovalRequest $contentRemovalRequest): void
    {
        if (ContentRemovalRequestStatus::Pending !== $contentRemovalRequest->getStatus()) {
            throw new BadRequestHttpException('Cette demande a déjà été traitée.');
        }
    }

    private function removeEventImage(Event $event): void
    {
        $event->setImageFile();
        $event->setImageHash(null);
        $event->setImageSystemFile();
        $event->setImageSystemHash(null);
    }

    private function markAsProcessed(ContentRemovalRequest $contentRemovalRequest): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $contentRemovalRequest->setStatus(ContentRemovalRequestStatus::Processed);
        $contentRemovalRequest->setProcessedAt(new DateTimeImmutable());
        $contentRemovalRequest->setProcessedBy($user);
    }

    private function markAsRejected(ContentRemovalRequest $contentRemovalRequest): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $contentRemovalRequest->setStatus(ContentRemovalRequestStatus::Rejected);
        $contentRemovalRequest->setProcessedAt(new DateTimeImmutable());
        $contentRemovalRequest->setProcessedBy($user);
    }
}
