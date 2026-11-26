<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A member following a publisher or a venue: they hear by e-mail of its new events (App\Follow\FollowAlertSender).
 * Exactly one of the two is set.
 */
#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\UniqueConstraint(name: 'follow_publisher_unique', columns: ['user_id', 'publisher_id'])]
#[ORM\UniqueConstraint(name: 'follow_place_unique', columns: ['user_id', 'place_id'])]
class Follow
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Publisher::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Publisher $publisher = null;

    #[ORM\ManyToOne(targetEntity: Place::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Place $place = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPublisher(): ?Publisher
    {
        return $this->publisher;
    }

    public function setPublisher(?Publisher $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): self
    {
        $this->place = $place;

        return $this;
    }

    /**
     * The publisher or the venue followed.
     */
    public function getTarget(): Publisher|Place
    {
        return $this->publisher ?? $this->place ?? throw new LogicException('A follow follows a publisher or a venue.');
    }

    public function setTarget(Publisher|Place $target): self
    {
        $this->publisher = $target instanceof Publisher ? $target : null;
        $this->place = $target instanceof Place ? $target : null;

        return $this;
    }
}
