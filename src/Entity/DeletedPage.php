<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\DeletedPageKind;
use App\Repository\DeletedPageRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The page of a deleted event, member, publisher or venue, which answers 410 Gone rather than 404 (DeletedPageListener
 * writes it). Kept when another one takes the slug: the page is found first, and answers again.
 */
#[ORM\Entity(repositoryClass: DeletedPageRepository::class)]
#[ORM\UniqueConstraint(name: 'deleted_page_unique', columns: ['kind', 'identifier'])]
class DeletedPage
{
    use EntityIdentityTrait;

    /** Null for the events and members deleted before the record, found from the gaps of their ids */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 16, enumType: DeletedPageKind::class)]
        private DeletedPageKind $kind,

        /** What the URL names it by: an event's or a member's id, a publisher's slug, a venue's "<location slug>/<slug>" */
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $identifier,
    ) {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function getKind(): DeletedPageKind
    {
        return $this->kind;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
