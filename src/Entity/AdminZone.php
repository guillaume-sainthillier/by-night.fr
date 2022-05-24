<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\AdminZoneRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\VirtualProperty;
use Stringable;

/**
 * OAuth.
 */
#[ORM\Index(name: 'admin_zone_type_name_idx', columns: ['type', 'name'])]
#[ORM\Index(name: 'admin_zone_type_population_idx', columns: ['type', 'population'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 10)]
#[ORM\DiscriminatorMap(['PPL' => 'City', 'ADM1' => 'AdminZone1', 'ADM2' => 'AdminZone2'])]
#[ORM\Entity(repositoryClass: AdminZoneRepository::class, readOnly: true)]
#[ORM\HasLifecycleCallbacks]
#[ExclusionPolicy('NONE')]
abstract class AdminZone implements Stringable
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[Groups(['elasticsearch:event:details', 'elasticsearch:city:details', 'elasticsearch:user:details'])]
    protected ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    #[Exclude]
    #[Gedmo\Slug(fields: ['name'])]
    protected ?string $slug = null;

    #[ORM\Column(type: 'string', length: 200)]
    #[Groups(['elasticsearch:city:details'])]
    protected ?string $name = null;

    #[ORM\Column(type: 'float')]
    protected float $latitude = 0.0;

    #[ORM\Column(type: 'float')]
    protected float $longitude = 0.0;

    #[ORM\Column(type: 'integer')]
    protected int $population = 0;

    #[ORM\ManyToOne(targetEntity: Country::class, fetch: 'EXTRA_LAZY')]
    protected ?Country $country = null;

    #[ORM\Column(name: 'admin1_code', type: 'string', length: 20, nullable: true)]
    #[Exclude]
    protected ?string $admin1Code = null;

    #[ORM\Column(name: 'admin2_code', type: 'string', length: 80, nullable: true)]
    #[Exclude]
    protected ?string $admin2Code = null;

    /**
     * @return float[]
     *
     * @psalm-return array{lat: float, lon: float}
     */
    #[Groups(['elasticsearch:city:details', 'elasticsearch:event:details'])]
    #[Expose]
    #[VirtualProperty]
    #[SerializedName('location')]
    public function getLocation(): array
    {
        return [
            'lat' => $this->latitude,
            'lon' => $this->longitude,
        ];
    }

    public function __toString(): string
    {
        return $this->name ?: '';
    }

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

    public function getPopulation(): ?int
    {
        return $this->population;
    }

    public function setPopulation(int $population): self
    {
        $this->population = $population;

        return $this;
    }

    public function getAdmin1Code(): ?string
    {
        return $this->admin1Code;
    }

    public function setAdmin1Code(?string $admin1Code): self
    {
        $this->admin1Code = $admin1Code;

        return $this;
    }

    public function getAdmin2Code(): ?string
    {
        return $this->admin2Code;
    }

    public function setAdmin2Code(?string $admin2Code): self
    {
        $this->admin2Code = $admin2Code;

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
}
