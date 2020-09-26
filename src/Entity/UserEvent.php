<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserEventRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="user_event_unique", columns={"user_id", "event_id"})
 * }
 * )
 */
class UserEvent
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $participe = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $interet = false;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="userEvents")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $user = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Event", inversedBy="userEvents")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?Event $event = null;

    public function __toString()
    {
        return '#' . $this->id ?: '?';
    }

    public function getParticipe(): ?bool
    {
        return $this->participe;
    }

    public function setParticipe(bool $participe): self
    {
        $this->participe = $participe;

        return $this;
    }

    public function getInteret(): ?bool
    {
        return $this->interet;
    }

    public function setInteret(bool $interet): self
    {
        $this->interet = $interet;

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
}
