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
use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use Stringable;

#[ORM\Entity(repositoryClass: CountryRepository::class, readOnly: true)]
#[ExclusionPolicy('NONE')]
class Country implements Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    #[ORM\Column(type: 'string', length: 2)]
    #[ORM\Id]
    #[Serializer\Groups(['elasticsearch:event:details', 'elasticsearch:user:details', 'elasticsearch:city:details'])]
    private ?string $id = null;

    #[ORM\Column(length: 63, unique: true)]
    #[Exclude]
    #[Gedmo\Slug(fields: ['name'], prefix: 'c--')]
    private ?string $slug = null;

    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    #[Exclude]
    private ?string $locale = null;

    #[ORM\Column(type: 'string', length: 63)]
    #[Serializer\Groups(['elasticsearch:city:details'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 63)]
    #[Serializer\Groups(['elasticsearch:city:details'])]
    private ?string $displayName = null;

    #[ORM\Column(type: 'string', length: 63)]
    #[Serializer\Groups(['elasticsearch:city:details'])]
    private ?string $atDisplayName = null;

    #[ORM\Column(type: 'string', length: 63)]
    #[Exclude]
    private ?string $capital = null;

    #[ORM\Column(type: 'string', length: 511, nullable: true)]
    #[Exclude]
    private ?string $postalCodeRegex = null;

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getKeyPrefix(): string
    {
        return 'country';
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return sprintf(
            '%s-id-%s',
            $this->getKeyPrefix(),
            $this->getId()
        );
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set id.
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCapital(): ?string
    {
        return $this->capital;
    }

    public function setCapital(string $capital): self
    {
        $this->capital = $capital;

        return $this;
    }

    public function getPostalCodeRegex(): ?string
    {
        return $this->postalCodeRegex;
    }

    public function setPostalCodeRegex(?string $postalCodeRegex): self
    {
        $this->postalCodeRegex = $postalCodeRegex;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getAtDisplayName(): ?string
    {
        return $this->atDisplayName;
    }

    public function setAtDisplayName(string $atDisplayName): self
    {
        $this->atDisplayName = $atDisplayName;

        return $this;
    }
}
