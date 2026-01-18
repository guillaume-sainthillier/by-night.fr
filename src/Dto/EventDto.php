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
use App\Entity\Event;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Reject\Reject;
use App\Validator\Constraints\EventConstraint;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Attribute\Uploadable;
use Vich\UploaderBundle\Mapping\Attribute\UploadableField;

#[Uploadable]
#[EventConstraint]
final class EventDto implements ExternalIdentifiableInterface, DependencyRequirableInterface, DependencyObjectInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DtoEntityIdentifierResolvableInterface
{
    use DtoExternalDateFilterableTrait;
    use DtoExternalIdentifiableTrait;

    public ?int $entityId = null;

    public ?UserDto $user = null;

    // For vich form upload
    public ?DateTimeImmutable $createdAt = null;

    // For vich form upload
    public ?DateTimeImmutable $updatedAt = null;

    // When used by users
    #[Assert\Valid]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image]
    #[UploadableField(mapping: 'event_image', fileNameProperty: 'image.name', size: 'image.size', mimeType: 'image.mimeType', originalName: 'image.originalName', dimensions: 'image.dimensions')]
    public ?File $imageFile = null;

    public ?EmbeddedFile $image = null;

    #[Assert\NotBlank(message: 'Vous devez donner une date à votre événement')]
    public ?DateTimeInterface $startDate = null;

    public ?DateTimeInterface $endDate = null;

    public ?string $fromData = null;

    #[Assert\NotBlank(message: "N'oubliez pas de nommer votre événement !")]
    #[Assert\Length(max: 255, maxMessage: 'Le nom de votre événement dépasse les 255 caractères autorisés !')]
    public ?string $name = null;

    #[Assert\NotBlank(message: "N'oubliez pas de décrire votre événement !")]
    public ?string $description = null;

    // When used by parsers
    public ?string $imageUrl = null;

    public ?string $prices = null;

    public ?string $hours = null;

    public ?string $source = null;

    public ?string $type = null;

    public ?string $status = null;

    public ?string $category = null;

    public ?string $theme = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?string $address = null;

    /** @var string[] */
    public ?array $websiteContacts = [];

    /** @var string[] */
    public ?array $phoneContacts = [];

    /** @var string[] */
    public ?array $emailContacts = [];

    public ?PlaceDto $place = null;

    public ?Reject $reject = null;

    public ?string $parserVersion = null;

    public ?string $parserName = null;

    public function __construct()
    {
        $this->image = new EmbeddedFile();
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     */
    public function setImageFile(?File $image = null): self
    {
        $this->imageFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

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

        if (null !== $this->user) {
            $catalogue->add(new Dependency($this->user));
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
            return \sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_id($this)
            );
        }

        return \sprintf(
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

        return \sprintf(
            '%s-id-%d',
            $this->getKeyPrefix(),
            $this->entityId
        );
    }
}
