<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\PublisherLegacySlugRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The slug of a deleted publisher whose events another one took: its page (/organisateurs/{slug}) redirects to that
 * one's (see PublisherRemover). Gone with that publisher.
 */
#[ORM\Entity(repositoryClass: PublisherLegacySlugRepository::class)]
class PublisherLegacySlug
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Publisher::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Publisher $publisher,

        #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
        private string $slug,
    ) {
    }

    public function getPublisher(): Publisher
    {
        return $this->publisher;
    }

    public function setPublisher(Publisher $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
