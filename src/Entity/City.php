<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Override;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: CityRepository::class)]
class City extends AdminZone implements InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    #[ORM\ManyToOne(targetEntity: AdminZone::class)]
    #[Groups(['elasticsearch:city:details'])]
    protected ?AdminZone $parent = null;

    /** @var Collection<int, ZipCity> */
    #[ORM\OneToMany(targetEntity: ZipCity::class, mappedBy: 'parent', fetch: 'EXTRA_LAZY')]
    protected Collection $zipCities;

    public function __construct()
    {
        $this->zipCities = new ArrayCollection();
    }

    #[Groups(['elasticsearch:city:details'])]
    #[SerializedName('country')]
    #[Override]
    public function getCountry(): ?Country
    {
        return parent::getCountry();
    }

    #[Override]
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

        return \sprintf(
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

        return \sprintf('%s (%s)', $this->getName(), implode(', ', $parts));
    }

    #[Groups(['elasticsearch:city:details'])]
    #[SerializedName('postalCodes')]
    public function getPostalCodes(): array
    {
        $postalCodes = [];
        foreach ($this->zipCities as $zipCity) {
            $postalCodes[] = $zipCity->getPostalCode();
        }

        return $postalCodes;
    }

    #[Override]
    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getParent(): ?AdminZone
    {
        return $this->parent;
    }

    public function setParent(?AdminZone $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, ZipCity>
     */
    public function getZipCities(): Collection
    {
        return $this->zipCities;
    }

    public function addZipCity(ZipCity $zipCity): static
    {
        if (!$this->zipCities->contains($zipCity)) {
            $this->zipCities[] = $zipCity;
            $zipCity->setParent($this);
        }

        return $this;
    }

    public function removeZipCity(ZipCity $zipCity): static
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
