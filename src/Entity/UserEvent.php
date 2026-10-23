<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Outing\OutingState;
use App\Repository\UserEventRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

#[ORM\Entity(repositoryClass: UserEventRepository::class)]
#[ORM\UniqueConstraint(name: 'user_event_unique', columns: ['user_id', 'event_id'])]
class UserEvent implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $going = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $wish = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'userEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Event $event = null;

    /**
     * What the member knows of the event (OutingState): as it was when they said "J'y vais", then as their last alert
     * told them. Null for a row older than the alerts, which the next check fills without an alert.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $knownState = null;

    /** When the member last heard of a change of the event: one alert a day at most */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $alertedAt = null;

    public function __toString(): string
    {
        return '#' . ($this->id ?? '?');
    }

    public function getGoing(): ?bool
    {
        return $this->going;
    }

    public function setGoing(bool $going): self
    {
        $this->going = $going;

        return $this;
    }

    public function getWish(): ?bool
    {
        return $this->wish;
    }

    public function setWish(bool $wish): self
    {
        $this->wish = $wish;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getKnownState(): ?OutingState
    {
        return null === $this->knownState ? null : OutingState::fromArray($this->knownState);
    }

    public function setKnownState(?OutingState $knownState): self
    {
        $this->knownState = $knownState?->toArray();

        return $this;
    }

    public function getAlertedAt(): ?DateTimeImmutable
    {
        return $this->alertedAt;
    }

    public function setAlertedAt(?DateTimeImmutable $alertedAt): self
    {
        $this->alertedAt = $alertedAt;

        return $this;
    }

    public function isGoing(): ?bool
    {
        return $this->going;
    }

    public function isWish(): ?bool
    {
        return $this->wish;
    }
}
