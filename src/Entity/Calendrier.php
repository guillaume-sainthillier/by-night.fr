<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Calendrier.
 *
 * @ORM\Entity(repositoryClass="App\Repository\CalendrierRepository")
 * @ORM\Table(name="Calendrier",
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="user_event_unique", columns={"user_id", "event_id"})
 *     }
 * )
 */
class Calendrier
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    /**
     *
     * @ORM\Column(type="boolean")
     */
    protected ?bool $participe = null;

    /**
     *
     * @ORM\Column(type="boolean")
     */
    protected ?bool $interet = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="calendriers")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?User $user = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Event", inversedBy="calendriers")
     * @ORM\JoinColumn(nullable=false)
     */
    protected ?Event $event = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->participe = false;
        $this->interet = false;
    }

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
