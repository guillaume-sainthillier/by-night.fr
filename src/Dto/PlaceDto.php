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
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Entity\Place;
use App\Reject\Reject;

class PlaceDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalIdentifiableTrait;

    public ?int $entityId = null;

    public ?string $name = null;

    public ?CityDto $city = null;

    public ?string $street = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?CountryDto $country = null;

    public ?Reject $reject = null;

    /**
     * {@inheritDoc}
     */
    public function getRequiredCatalogue(): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        if (null !== $this->city) {
            $catalogue->add(new Dependency($this->city));
        }

        if (null !== $this->country) {
            $catalogue->add(new Dependency($this->country));
        }

        return $catalogue;
    }

    public function getKeyPrefix(): string
    {
        return 'place';
    }

    public function getUniqueKey(): string
    {
        if (
            (null === $this->externalId || null === $this->externalOrigin)
            && null === $this->name
            && null === $this->street
        ) {
            return sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_id($this)
            );
        }

        if (null !== $this->externalId && null !== $this->externalOrigin) {
            return sprintf(
                '%s-external-%s-%s',
                $this->getKeyPrefix(),
                $this->externalId,
                $this->externalOrigin
            );
        }

        $placeKey = mb_strtolower(sprintf(
            '%s-%s',
            $this->name,
            $this->street
        ));

        if (null !== $this->city) {
            return sprintf(
                '%s-data-%s-%s',
                $this->getKeyPrefix(),
                $placeKey,
                $this->city->getUniqueKey()
            );
        }

        if (null !== $this->country) {
            return sprintf(
                '%s-data-%s-%s',
                $this->getKeyPrefix(),
                $placeKey,
                $this->country->getUniqueKey()
            );
        }

        return sprintf(
            '%s-data-%s',
            $this->getKeyPrefix(),
            $placeKey
        );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        \assert($entity instanceof Place);
        $this->entityId = $entity->getId();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->entityId) {
            return null;
        }

        return sprintf(
            '%s-id-%d',
            $this->getKeyPrefix(),
            $this->entityId
        );
    }
}
