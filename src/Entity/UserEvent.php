<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\UserEventRepository;
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

    public function __toString(): string
    {
        return '#' . $this->id ?: '?';
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

    public function isGoing(): ?bool
    {
        return $this->going;
    }

    public function isWish(): ?bool
    {
        return $this->wish;
    }
}
