<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Dto\EventDto;
use App\Dto\UserDto;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use App\Handler\DoctrineEventHandler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * The events members publish from their personal space. They are saved through the import pipeline like any other
 * (DoctrineEventHandler), then given what only a member's event has: its draft state, and on creation its author's
 * participation and first comment.
 *
 * DoctrineEventHandler clears the entity manager: the event and its author are taken back by reference afterwards.
 */
final readonly class MemberEventPublisher
{
    public function __construct(
        private DoctrineEventHandler $doctrineEventHandler,
        private EntityManagerInterface $entityManager,
        private EventParticipationManager $eventParticipationManager,
    ) {
    }

    /**
     * The event a member starts filling in.
     */
    public function createDto(User $author): EventDto
    {
        $userDto = new UserDto();
        $userDto->entityId = $author->getId();

        $dto = new EventDto();
        $dto->user = $userDto;

        return $dto;
    }

    /**
     * Saves a new event of the member, who goes, with their first comment if they wrote one.
     *
     * @param EventDto $dto an event of createDto()
     */
    public function create(EventDto $dto, bool $draft, ?string $comment): Event
    {
        $this->doctrineEventHandler->handleOne($dto);

        $event = $this->getEvent($dto);
        $event->setDraft($draft);
        $author = $this->entityManager->getReference(User::class, $dto->user?->entityId ?? throw new LogicException('The event has no author.'));
        if (null !== $comment && '' !== trim($comment)) {
            $this->entityManager->persist(new Comment()->setComment($comment)->setEvent($event)->setUser($author));
        }

        // Flushes the draft state and the comment along
        $this->eventParticipationManager->participate($author, $event, true);

        return $event;
    }

    /**
     * Saves the member's changes: the draft button takes the event off the site, the publish button puts a draft online.
     * An imported event changed by its member is dated as changed now, as a change of its source would be.
     */
    public function update(EventDto $dto, bool $draft): Event
    {
        if (null !== $dto->externalId) {
            $dto->externalUpdatedAt = new DateTimeImmutable();
        }

        $this->doctrineEventHandler->handleOne($dto);

        $event = $this->getEvent($dto);
        $event->setDraft($draft);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Saves a batch of a member's imported events (App\Transfer\Import\EventImportRunner) in one go through the
     * pipeline, then gives each what only a member's event has: a new one is a draft unless the file says otherwise,
     * and its author goes; a changed one keeps its draft state unless the file says otherwise. Both take the
     * member's reference.
     *
     * @param list<array{dto: EventDto, new: bool, draft: ?bool, reference: ?string}> $items
     *
     * @return list<?int> the saved event of each item, null for one the pipeline turned down
     */
    public function saveImported(User $author, array $items): array
    {
        foreach ($items as $item) {
            if (!$item['new'] && null !== $item['dto']->externalId) {
                $item['dto']->externalUpdatedAt = new DateTimeImmutable();
            }
        }

        $this->doctrineEventHandler->handleMany(array_column($items, 'dto'));

        $author = $this->entityManager->getReference(User::class, $author->getId());
        $ids = [];
        $created = [];
        foreach ($items as $item) {
            $dto = $item['dto'];
            if (null === $dto->entityId || !($dto->reject?->isValid() ?? true) || !($dto->place?->reject?->isValid() ?? true)) {
                $ids[] = null;

                continue;
            }

            $event = $this->getEvent($dto);
            $draft = $item['new'] ? ($item['draft'] ?? true) : $item['draft'];
            if (null !== $draft) {
                $event->setDraft($draft);
            }

            if (null !== $item['reference']) {
                $event->setExternalReference($item['reference']);
            }

            if ($item['new']) {
                $created[] = $event;
            }

            $ids[] = $dto->entityId;
        }

        $this->entityManager->flush();
        foreach ($created as $event) {
            $this->eventParticipationManager->participate($author, $event, true);
        }

        return $ids;
    }

    private function getEvent(EventDto $dto): Event
    {
        return $this->entityManager->getReference(Event::class, $dto->entityId ?? throw new LogicException('The event was not saved.'));
    }
}
