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
     *
     * @ORM\Column(type="string", length=2)
     * @ORM\Id
     * @Serializer\Groups({"list_event", "list_user", "list_city"})
     */
    private $id;

    /**
     * @Gedmo\Slug(fields={"name"})
     * @ORM\Column(length=63, unique=true)
     * @Exclude
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=5, nullable=true)
     * @Exclude
     */
    private $locale;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=63)
     * @Serializer\Groups({"list_city"})
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=63)
     * @Exclude
     */
    private $capital;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=511, nullable=true)
     * @Exclude
     */
    private $postalCodeRegex;

    /**
     * Set id.
     *
     * @param string $id
     *
     * @return Country
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get id.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set locale.
     *
     * @param string $locale
     *
     * @return Country
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Country
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set capital.
     *
     * @param string $capital
     *
     * @return Country
     */
    public function setCapital($capital)
    {
        $this->capital = $capital;

        return $this;
    }

    /**
     * Get capital.
     *
     * @return string
     */
    public function getCapital()
    {
        return $this->capital;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     *
     * @return Country
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @return string|null
     */
    public function getPostalCodeRegex(): ?string
    {
        return $this->postalCodeRegex;
    }

    /**
     * @param string|null $postalCodeRegex
     * @return Country
     */
    public function setPostalCodeRegex(?string $postalCodeRegex): Country
    {
        $this->postalCodeRegex = $postalCodeRegex;
        return $this;
    }
}
