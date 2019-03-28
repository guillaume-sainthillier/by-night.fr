<?php

namespace App\Entity;

use App\Reject\Reject;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exploration.
 *
 * @ORM\Table(name="Exploration")
 * @ORM\Entity
 * @ORM\Entity(repositoryClass="App\Repository\ExplorationRepository", readOnly=true)
 */
class Exploration
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", options={"unsigned"=true})
     * @ORM\Id
     */
    protected $id;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="last_updated", type="datetime", nullable=true)
     */
    private $lastUpdated;

    /**
     * @var bool
     *
     * @ORM\Column(name="reason", type="integer", nullable=false)
     */
    private $reason;

    /**
     * @var string
     *
     * @ORM\Column(name="firewall_version", type="string", length=7)
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

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
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
}
