<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\CrossSourceLinkRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Two events of two sources found to be the same show (App\Import\CrossSource\CrossSourceLinker): the evidence
 * EventFamilyResolver gathers them on, as it gathers the rows of one source on their identity hash. The lower id
 * comes first, so a pair is stored once.
 *
 * A link kept apart is a pair someone found to be two shows: it no longer gathers them, and the linker never makes it
 * again (app:events:link-cross-source --keep-apart).
 */
#[ORM\Entity(repositoryClass: CrossSourceLinkRepository::class)]
#[ORM\UniqueConstraint(name: 'cross_source_link_pair_unique', columns: ['event_id', 'linked_event_id'])]
#[ORM\Index(name: 'cross_source_link_linked_event_idx', columns: ['linked_event_id'])]
class CrossSourceLink
{
    use EntityIdentityTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $keptApart = false;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Event $event,

        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Event $linkedEvent,
    ) {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getLinkedEvent(): Event
    {
        return $this->linkedEvent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isKeptApart(): bool
    {
        return $this->keptApart;
    }

    public function setKeptApart(bool $keptApart): self
    {
        $this->keptApart = $keptApart;

        return $this;
    }
}
