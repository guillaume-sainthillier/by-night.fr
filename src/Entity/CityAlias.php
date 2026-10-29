<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Comparator\CityComparator;
use App\Repository\CityAliasRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Another name the sources give a city: the official name of the commune it belongs to when GeoNames knows only its
 * villages (a commune nouvelle, "Piré-Chancé" for Piré-sur-Seiche), a commune déléguée, a municipal arrondissement
 * ("Paris 9e Arrondissement", "PARIS 9EME"). Read by CityComparator once neither a city's name nor the postal code finds the town; written by
 * `app:cities:import-official-data` (CityOfficialDataImporter), which replaces them all.
 */
#[ORM\Entity(repositoryClass: CityAliasRepository::class)]
#[ORM\Index(name: 'city_alias_name_key_idx', columns: ['name_key'])]
#[ORM\UniqueConstraint(name: 'city_alias_city_name_key_unique', columns: ['city_id', 'name_key'])]
class CityAlias
{
    use EntityIdentityTrait;

    /** The name as CityComparator compares it, which the lookups match whatever the database's collation */
    #[ORM\Column(type: Types::STRING, length: 200)]
    private string $nameKey;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: City::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private City $city,

        #[ORM\Column(type: Types::STRING, length: 200)]
        private string $name,
    ) {
        $this->nameKey = CityComparator::nameKey($name);
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNameKey(): string
    {
        return $this->nameKey;
    }
}
