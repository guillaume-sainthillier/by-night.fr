<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CityRepository::class, readOnly: true)]
#[ExclusionPolicy('NONE')]
class City extends AdminZone implements InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    #[ORM\ManyToOne(targetEntity: AdminZone::class, fetch: 'EAGER')]
    #[Groups(['list_city'])]
    protected ?AdminZone $parent = null;

    #[Groups(['list_city'])]
    protected ?Country $country = null;

    #[ORM\OneToMany(targetEntity: ZipCity::class, fetch: 'EXTRA_LAZY', mappedBy: 'parent')]
    protected ?Collection $zipCities = null;

    public function __construct()
    {
        $this->zipCities = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    public function getKeyPrefix(): string
    {
        return 'city';
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return sprintf(
            '%s-id-%d',
            $this->getKeyPrefix(),
            $this->getId()
        );
    }

    public function getFullName(): string
    {
        $parts = [];
        if (null !== $this->getParent()) {
            $parts[] = $this->getParent()->getName();
        }

        $parts[] = $this->getCountry()->getName();

        return sprintf('%s (%s)', $this->getName(), implode(', ', $parts));
    }

    /**
     * Get parent.
     */
    public function getParent(): ?AdminZone
    {
        return $this->parent;
    }

    /**
     * Set parent.
     */
    public function setParent(AdminZone $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): AdminZone
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return Collection<int, ZipCity>
     */
    public function getZipCities(): Collection
    {
        return $this->zipCities;
    }

    public function addZipCity(ZipCity $zipCity): self
    {
        if (!$this->zipCities->contains($zipCity)) {
            $this->zipCities[] = $zipCity;
            $zipCity->setParent($this);
        }

        return $this;
    }

    public function removeZipCity(ZipCity $zipCity): self
    {
        if ($this->zipCities->removeElement($zipCity)) {
            // set the owning side to null (unless already changed)
            if ($zipCity->getParent() === $this) {
                $zipCity->setParent(null);
            }
        }

        return $this;
    }
}
