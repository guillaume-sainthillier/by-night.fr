<?php

namespace App\Entity;

use App\Reject\Reject;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exploration.
 *
 * @ORM\Table(name="Exploration", indexes={
 *     @ORM\Index(name="exploration_external_id_idx", columns={"external_id"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\ExplorationRepository")
 */
class Exploration
{
    use EntityIdentityTrait;

    /**
     * @ORM\Column(type="string", length=127)
     */
    protected $externalId;

    /**
     * @var DateTime
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastUpdated;

    /**
     * @var bool
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $reason;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=7)
     */
    private $firewallVersion;

    /**
     * @var Reject
     */
    private $reject;

    public function setReject(Reject $reject)
    {
        $this->reject = $reject;

        return $this;
    }

    public function getReject(): ?Reject
    {
        return $this->reject;
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

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

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
}
