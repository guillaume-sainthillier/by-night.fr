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
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Entity\Place;
use App\Reject\Reject;

class PlaceDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalIdentifiableTrait;

    /** @var int|null */
    public $entityId;

    /** @var string|null */
    public $name;

    /** @var string|null */
    public $postalCode;

    /** @var CityDto|null */
    public $city;

    /** @var string|null */
    public $street;

    /** @var float|null */
    public $latitude;

    /** @var float|null */
    public $longitude;

    /** @var CountryDto|null */
    public $country;

    /** @var Reject|null */
    public $reject;

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

    public function getUniqueKey(): string
    {
        if (null !== $this->externalId && null !== $this->externalOrigin) {
            return sprintf(
                '%s-%s',
                $this->externalId,
                $this->externalOrigin
            );
        }

        $placeKey = sprintf(
            '%s-%s-%s',
            $this->name,
            $this->postalCode,
            $this->street
        );

        if ($this->city) {
            return sprintf(
                'place-u-%s-%s',
                $placeKey,
                $this->city->getUniqueKey()
            );
        }

        if ($this->country) {
            return sprintf(
                'place-u-%s-%s',
                $placeKey,
                $this->country->getUniqueKey()
            );
        }

        return sprintf(
            'place-u-%s',
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

        return sprintf('place-%s', $this->entityId);
    }
}
