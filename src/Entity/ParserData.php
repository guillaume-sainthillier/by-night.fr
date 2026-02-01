<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Reject\Reject;
use App\Repository\ParserDataRepository;
use App\Utils\UnitOfWorkOptimizer;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParserDataRepository::class)]
#[ORM\Index(name: 'parser_data_idx', columns: ['external_id', 'external_origin'])]
class ParserData
{
    use EntityIdentityTrait;

    #[ORM\Column(type: Types::STRING, length: 127)]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::STRING, length: 63)]
    private ?string $externalOrigin = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastUpdated = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $reason = Reject::VALID;

    #[ORM\Column(type: Types::STRING, length: 7)]
    private string $firewallVersion = '1.0';

    #[ORM\Column(type: Types::STRING, length: 7, nullable: true)]
    private ?string $parserVersion = null;

    private ?Reject $reject = null;

    public function getReject(): ?Reject
    {
        return $this->reject;
    }

    public function setReject(Reject $reject): self
    {
        $this->reject = $reject;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getLastUpdated(): ?DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?DateTimeImmutable $lastUpdated): self
    {
        $this->lastUpdated = UnitOfWorkOptimizer::getDateTimeValue($this->lastUpdated, $lastUpdated);

        return $this;
    }

    public function getReason(): ?int
    {
        return $this->reason;
    }

    public function setReason(int $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function getFirewallVersion(): ?string
    {
        return $this->firewallVersion;
    }

    public function setFirewallVersion(string $firewallVersion): self
    {
        $this->firewallVersion = $firewallVersion;

        return $this;
    }

    public function getParserVersion(): ?string
    {
        return $this->parserVersion;
    }

    public function setParserVersion(?string $parserVersion): self
    {
        $this->parserVersion = $parserVersion;

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
}
