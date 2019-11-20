<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Table(name="zip_city", indexes={
 *     @ORM\Index(name="zip_city_postal_code_name_idx", columns={"country_id", "postal_code", "name"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\ZipCityRepository", readOnly=true)
 * @Serializer\ExclusionPolicy("ALL")
 */
class ZipCity
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue("AUTO")
     * @Serializer\Groups({"list_event", "list_city", "list_user"})
     * @Serializer\Expose
     */
    protected $id;

    /**
     * @Gedmo\Slug(fields={"postalCode", "name"}, updatable=false)
     * @ORM\Column(length=201, unique=true)
     */
    protected $slug;

    /**
     * @var Country
     * @ORM\ManyToOne(targetEntity="App\Entity\Country", fetch="EXTRA_LAZY")
     */
    protected $country;

    /**
     * @var string
     * @ORM\Column(type="string", length=20)
     */
    protected $postalCode;

    /**
     * @var string
     * @ORM\Column(type="string", length=180)
     */
    protected $name;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    protected $latitude;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    protected $longitude;

    /**
     * @var string
     * @ORM\Column(name="admin1_code", type="string", length=20)
     */
    protected $admin1Code;

    /**
     * @var string|null
     * @ORM\Column(name="admin1_name", type="string", length=100, nullable=true)
     */
    protected $admin1Name;

    /**
     * @var string
     * @ORM\Column(name="admin2_code", type="string", length=80)
     */
    protected $admin2Code;

    /**
     * @var string|null
     * @ORM\Column(name="admin2_name", type="string", length=100, nullable=true)
     */
    protected $admin2Name;

    /**
     * @var City
     * @ORM\ManyToOne(targetEntity="App\Entity\City", fetch="EXTRA_LAZY")
     */
    protected $parent;

    /**
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;

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

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAdmin1Code(): ?string
    {
        return $this->admin1Code;
    }

    public function setAdmin1Code(string $admin1Code): self
    {
        $this->admin1Code = $admin1Code;

        return $this;
    }

    public function getAdmin1Name(): ?string
    {
        return $this->admin1Name;
    }

    public function setAdmin1Name(?string $admin1Name): self
    {
        $this->admin1Name = $admin1Name;

        return $this;
    }

    public function getAdmin2Code(): ?string
    {
        return $this->admin2Code;
    }

    public function setAdmin2Code(string $admin2Code): self
    {
        $this->admin2Code = $admin2Code;

        return $this;
    }

    public function getAdmin2Name(): ?string
    {
        return $this->admin2Name;
    }

    public function setAdmin2Name(?string $admin2Name): self
    {
        $this->admin2Name = $admin2Name;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getParent(): ?City
    {
        return $this->parent;
    }

    public function setParent(?City $parent): self
    {
        $this->parent = $parent;

        return $this;
    }
}
