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
use App\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CityRepository::class, readOnly: true)]
#[ExclusionPolicy('NONE')]
class City extends AdminZone implements InternalIdentifiableInterface
{
    #[ORM\ManyToOne(targetEntity: AdminZone::class, fetch: 'EAGER')]
    #[Groups(['list_city'])]
    protected ?AdminZone $parent = null;

    #[Groups(['list_city'])]
    protected ?Country $country = null;

    public function __toString(): string
    {
        return $this->getFullName();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return sprintf('city-%s', $this->getId());
    }

    public function getFullName(): string
    {
        $parts = [];
        if ($this->getParent()) {
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
}
