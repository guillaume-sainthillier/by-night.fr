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
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Entity\Place;
use App\Reject\Reject;
use App\Utils\ObjectKey;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @implements DtoEntityIdentifierResolvableInterface<Place>
 */
final class PlaceDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalIdentifiableTrait;

    public ?int $entityId = null;

    #[Assert\NotBlank(message: 'Vous devez indiquer le lieu de votre événement')]
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
        return Place::KEY_PREFIX;
    }

    public function getUniqueKey(): string
    {
        if (null !== $this->externalId && null !== $this->externalOrigin) {
            return ObjectKey::external($this->getKeyPrefix(), $this->externalOrigin, $this->externalId);
        }

        if (null === $this->name && null === $this->street) {
            return ObjectKey::transient($this->getKeyPrefix(), $this);
        }

        return ObjectKey::data(
            $this->getKeyPrefix(),
            mb_strtolower($this->name ?? ''),
            mb_strtolower($this->street ?? ''),
            $this->city?->getUniqueKey() ?? $this->country?->getUniqueKey() ?? '',
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
