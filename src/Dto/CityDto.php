<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use App\Contracts\DependencyCatalogueInterface;
use App\Contracts\DependencyObjectInterface;
use App\Contracts\DependencyRequirableInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Entity\City;

class CityDto implements DependencyRequirableInterface, DependencyObjectInterface, DtoEntityIdentifierResolvableInterface, InternalIdentifiableInterface
{
    /** @var int|null */
    public $entityId;

    /** @var string|null */
    public $name;

    /** @var CountryDto|null */
    public $country;

    /**
     * {@inheritDoc}
     */
    public function getRequiredCatalogue(): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        if (null !== $this->country) {
            $catalogue->add(new Dependency($this->country));
        }

        return $catalogue;
    }

    public function getUniqueKey(): string
    {
        $cityKey = $this->name
            ?? spl_object_id($this);

        if (null === $this->country) {
            return sprintf('city-u-%s', $cityKey);
        }

        return sprintf(
            'city-u-%s-%s',
            $cityKey,
            $this->country->getUniqueKey()
        );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        \assert($entity instanceof City);
        $this->entityId = $entity->getId();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return sprintf('city-%s', $this->entityId);
    }
}
