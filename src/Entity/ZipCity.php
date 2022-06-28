<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\ZipCityRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Index(name: 'zip_city_postal_code_name_idx', columns: ['country_id', 'postal_code', 'name'])]
#[ORM\Entity(repositoryClass: ZipCityRepository::class, readOnly: true)]
#[Serializer\ExclusionPolicy('ALL')]
class ZipCity
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue('AUTO')]
    #[Serializer\Groups(['elasticsearch:event:details', 'elasticsearch:city:details', 'elasticsearch:user:details'])]
    #[Serializer\Expose]
    private ?int $id = null;

    #[ORM\Column(length: 201, unique: true)]
    #[Gedmo\Slug(fields: ['postalCode', 'name'])]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Country::class, fetch: 'EXTRA_LAZY')]
    private ?Country $country = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 180)]
    private ?string $name = null;

    #[ORM\Column(type: 'float')]
    private float $latitude = 0.0;

    #[ORM\Column(type: 'float')]
    private float $longitude = 0.0;

    #[ORM\Column(name: 'admin1_code', type: 'string', length: 20)]
    private ?string $admin1Code = null;

    #[ORM\Column(name: 'admin1_name', type: 'string', length: 100, nullable: true)]
    private ?string $admin1Name = null;

    #[ORM\Column(name: 'admin2_code', type: 'string', length: 80)]
    private ?string $admin2Code = null;

    #[ORM\Column(name: 'admin2_name', type: 'string', length: 100, nullable: true)]
    private ?string $admin2Name = null;

    #[ORM\ManyToOne(targetEntity: City::class, fetch: 'EXTRA_LAZY', inversedBy: 'zipCities')]
    private ?City $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
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
