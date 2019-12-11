<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;

/**
 * News.
 *
 * @ORM\Table(name="country")
 * @ORM\Entity(repositoryClass="App\Repository\CountryRepository", readOnly=true)
 * @ExclusionPolicy("NONE")
 */
class Country
{
    /**
     * @var string
     * @ORM\Column(type="string", length=2)
     * @ORM\Id
     * @Serializer\Groups({"list_event", "list_user", "list_city"})
     */
    private $id;

    /**
     * @var string
     * @Gedmo\Slug(fields={"name"}, prefix="c--")
     * @ORM\Column(length=63, unique=true)
     * @Exclude
     */
    private $slug;

    /**
     * @var string
     * @ORM\Column(type="string", length=5, nullable=true)
     * @Exclude
     */
    private $locale;

    /**
     * @var string
     * @ORM\Column(type="string", length=63)
     * @Serializer\Groups({"list_city"})
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(type="string", length=63)
     * @Serializer\Groups({"list_city"})
     */
    private $displayName;

    /**
     * @var string
     * @ORM\Column(type="string", length=63)
     * @Serializer\Groups({"list_city"})
     */
    private $atDisplayName;

    /**
     * @var string
     * @ORM\Column(type="string", length=63)
     * @Exclude
     */
    private $capital;

    /**
     * @var string|null
     * @ORM\Column(type="string", length=511, nullable=true)
     * @Exclude
     */
    private $postalCodeRegex;

    public function __toString()
    {
        return $this->name;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set id.
     *
     * @return Country
     */
    public function setId(string $id)
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
