<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use App\Contracts\PrefixableObjectKeyInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Entity\City;
use App\Utils\ObjectKey;

/**
 * @implements DtoEntityIdentifierResolvableInterface<City>
 */
final class CityDto implements DependencyRequirableInterface, DependencyObjectInterface, DtoEntityIdentifierResolvableInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    public ?int $entityId = null;

    public ?string $name = null;

    public ?string $postalCode = null;

    public ?CountryDto $country = null;

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

    public function getKeyPrefix(): string
    {
        return City::KEY_PREFIX;
    }

    public function getUniqueKey(): string
    {
        if (null === $this->name) {
            return ObjectKey::transient($this->getKeyPrefix(), $this);
        }

        // With the postal code: namesakes in two départements are two cities (see CityComparator)
        return ObjectKey::data(
            $this->getKeyPrefix(),
            mb_strtolower($this->name),
            $this->postalCode ?? '',
            $this->country?->getUniqueKey() ?? '',
        );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        $this->entityId = $entity->getId();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return ObjectKey::internal($this->getKeyPrefix(), $this->entityId);
    }
}
