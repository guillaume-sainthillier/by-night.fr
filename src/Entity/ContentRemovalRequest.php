<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use App\Repository\ContentRemovalRequestRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity(repositoryClass: ContentRemovalRequestRepository::class)]
class ContentRemovalRequest implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ContentRemovalType::class)]
    private ?ContentRemovalType $type = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $eventUrls = null;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\ManyToMany(targetEntity: Event::class)]
    #[ORM\JoinTable(name: 'content_removal_request_event')]
    private Collection $events;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ContentRemovalRequestStatus::class)]
    private ContentRemovalRequestStatus $status = ContentRemovalRequestStatus::Pending;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $processedBy = null;

    public function __construct()
    {
        $this->events = new ArrayCollection();
    }

    public function __toString(): string
    {
        return \sprintf('#%d - %s', $this->id ?? 0, $this->email ?? '');
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getType(): ?ContentRemovalType
    {
        return $this->type;
    }

    public function setType(?ContentRemovalType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getEventUrls(): ?array
    {
        return $this->eventUrls;
    }

    public function setEventUrls(?array $eventUrls): self
    {
        $this->eventUrls = $eventUrls;

        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }

        return $this;
    }

    public function removeEvent(Event $event): self
    {
        $this->events->removeElement($event);

        return $this;
    }

    public function getStatus(): ContentRemovalRequestStatus
    {
        return $this->status;
    }

    public function setStatus(ContentRemovalRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getProcessedBy(): ?User
    {
        return $this->processedBy;
    }

    public function setProcessedBy(?User $processedBy): self
    {
        $this->processedBy = $processedBy;

        return $this;
    }
}
