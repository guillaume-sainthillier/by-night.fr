<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Contracts\ExternalIdentifiableInterface;
use App\Repository\PlaceMetadataRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceMetadataRepository::class)]

#[ORM\Index(name: 'place_metadata_idx', columns: ['external_id', 'external_origin'])]
class PlaceMetadata implements ExternalIdentifiableInterface
{
    use EntityIdentityTrait;
    #[ORM\ManyToOne(targetEntity: Place::class, inversedBy: 'metadatas')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Place $place = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 63)]
    private ?string $externalOrigin = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $externalUpdatedAt = null;

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalOrigin(): ?string
    {
        return $this->externalOrigin;
    }

    public function setExternalOrigin(string $externalOrigin): self
    {
        $this->externalOrigin = $externalOrigin;

        return $this;
    }

    public function getExternalUpdatedAt(): ?DateTimeInterface
    {
        return $this->externalUpdatedAt;
    }

    public function setExternalUpdatedAt(?DateTimeInterface $externalUpdatedAt): self
    {
        $this->externalUpdatedAt = $externalUpdatedAt;

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
}
