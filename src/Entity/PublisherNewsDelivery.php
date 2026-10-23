<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\PublisherNewsEdition;
use App\Repository\PublisherNewsDeliveryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An edition of the publishers' news sent to a member (App\Newsletter\PublisherNewsSender): a run again, after a crash
 * or with a new member, skips them. The edition is kept as a string, so that an old edition's case can be removed.
 */
#[ORM\Entity(repositoryClass: PublisherNewsDeliveryRepository::class)]
#[ORM\UniqueConstraint(name: 'publisher_news_delivery_unique', columns: ['user_id', 'edition'])]
class PublisherNewsDelivery
{
    use EntityIdentityTrait;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $edition;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $sentAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
        PublisherNewsEdition $edition,
    ) {
        $this->edition = $edition->value;
        $this->sentAt = new DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEdition(): string
    {
        return $this->edition;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }
}
