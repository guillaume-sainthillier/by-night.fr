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
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

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

    public function getReject()
    {
        return $this->reject;
    }

    /**
     * Set lastUpdated.
     *
     * @param DateTime $lastUpdated
     *
     * @return Exploration
     */
    public function setLastUpdated($lastUpdated = null)
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    /**
     * Get lastUpdated.
     *
     * @return DateTime
     */
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }

    /**
     * Set reason.
     *
     * @param int $reason
     *
     * @return Exploration
     */
    public function setReason($reason)
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Get reason.
     *
     * @return int
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set firewallVersion.
     *
     * @param string $firewallVersion
     *
     * @return Exploration
     */
    public function setFirewallVersion($firewallVersion)
    {
        $this->firewallVersion = $firewallVersion;

        return $this;
    }

    /**
     * Get firewallVersion.
     *
     * @return string
     */
    public function getFirewallVersion()
    {
        return $this->firewallVersion;
    }

    public function getId(): ?int
    {
        return $this->id;
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
}
