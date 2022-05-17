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
use App\Entity\Event;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Reject\Reject;
use DateTimeInterface;

class EventDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalDateFilterableTrait;
    use DtoExternalIdentifiableTrait;

    /** @var int|null */
    public $entityId;

    /** @var DateTimeInterface|null */
    public $startDate;

    /** @var DateTimeInterface|null */
    public $endDate;

    /** @var string|null */
    public $name;

    /** @var string|null */
    public $description;

    /** @var string|null */
    public $imageUrl;

    /** @var string|null */
    public $prices;

    /** @var string|null */
    public $hours;

    /** @var string|null */
    public $source;

    /** @var string|null */
    public $type;

    /** @var string|null */
    public $status;

    /** @var string|null */
    public $category;

    /** @var string|null */
    public $theme;

    /** @var float|null */
    public $latitude;

    /** @var float|null */
    public $longitude;

    /** @var string|null */
    public $address;

    /** @var string[] */
    public $websiteContacts = [];

    /** @var string[] */
    public $phoneContacts = [];

    /** @var string[] */
    public $emailContacts = [];

    /** @var PlaceDto|null */
    public $place;

    /** @var Reject|null */
    public $reject;

    /** @var string|null */
    public $parserVersion;

    /** @var string|null */
    public $parserName;

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

    public function getUniqueKey(): string
    {
        if (null !== $this->externalId && null !== $this->externalOrigin) {
            return sprintf(
                '%s-%s',
                $this->externalId,
                $this->externalOrigin
            );
        }

        return spl_object_id($this);
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

        return sprintf('event-%s', $this->entityId);
    }
}
