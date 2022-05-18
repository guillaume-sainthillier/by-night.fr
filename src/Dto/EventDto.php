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
use App\Entity\Event;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Reject\Reject;
use DateTimeInterface;

class EventDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalDateFilterableTrait;
    use DtoExternalIdentifiableTrait;

    public ?int $entityId;

    public ?DateTimeInterface $startDate;

    public ?DateTimeInterface $endDate;

    public ?string $name;

    public ?string $description;

    public ?string $imageUrl;

    public ?string $prices;

    public ?string $hours;

    public ?string $source;

    public ?string $type;

    public ?string $status;

    public ?string $category;

    public ?string $theme;

    public ?float $latitude;

    public ?float $longitude;

    public ?string $address;

    /** @var string[] */
    public ?array $websiteContacts = [];

    /** @var string[] */
    public ?array $phoneContacts = [];

    /** @var string[] */
    public ?array $emailContacts = [];

    public ?PlaceDto $place;

    public ?Reject $reject;

    public ?string $parserVersion;

    public ?string $parserName;

    public function isAffiliate(): bool
    {
        return \in_array($this->parserName, [
            FnacSpectaclesAwinParser::getParserName(),
            DigitickAwinParser::getParserName(),
        ], true);
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredCatalogue(): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        if (null !== $this->place) {
            $catalogue->add(new Dependency($this->place, false));
        }

        return $catalogue;
    }

    public function getKeyPrefix(): string
    {
        return 'event';
    }

    public function getUniqueKey(): string
    {
        if (null === $this->externalId || null === $this->externalOrigin) {
            return sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_hash($this)
            );
        }

        return sprintf(
            '%s-external-%s-%s',
            $this->getKeyPrefix(),
            $this->externalId,
            $this->externalOrigin
        );
    }

    public function setIdentifierFromEntity(object $entity): void
    {
        \assert($entity instanceof Event);
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
