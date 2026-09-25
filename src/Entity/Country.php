<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Picture\ImageFormats;
use App\Repository\CountryRepository;
use App\Utils\ObjectKey;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * Read far more than written (every place and event points to one): DEFERRED_EXPLICIT keeps
 * flushes from diffing the loaded countries, only an explicit persist() writes one (GeoNames
 * importer, back-office).
 */
#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: CountryRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Country implements Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    /** The prefix of this entity's keys and of its DTO's, see ObjectKey */
    final public const string KEY_PREFIX = 'country';

    #[ORM\Column(type: Types::STRING, length: 2)]
    #[ORM\Id]
    #[Groups(['elasticsearch:event:details', 'elasticsearch:user:details', 'elasticsearch:city:details'])]
    private ?string $id = null;

    #[ORM\Column(length: 63, unique: true)]
    #[Ignore]
    #[Gedmo\Slug(fields: ['name'], prefix: 'c--')]
    private ?string $slug = null;

    #[ORM\Column(type: Types::STRING, length: 5, nullable: true)]
    #[Ignore]
    private ?string $locale = null;

    #[ORM\Column(type: Types::STRING, length: 63)]
    #[Groups(['elasticsearch:city:details'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 63)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::STRING, length: 63)]
    private ?string $atDisplayName = null;

    #[ORM\Column(type: Types::STRING, length: 63)]
    #[Ignore]
    private ?string $capital = null;

    #[ORM\Column(type: Types::STRING, length: 511, nullable: true)]
    #[Ignore]
    private ?string $postalCodeRegex = null;

    /** Punchline of the country portal, under its name */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Ignore]
    #[Assert\Length(max: 255)]
    private ?string $headline = null;

    /** Editorial introduction of the country portal (HTML from the back-office editor) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Ignore]
    private ?string $description = null;

    #[Vich\UploadableField(mapping: 'country_image', fileNameProperty: 'heroImage.name', size: 'heroImage.size', mimeType: 'heroImage.mimeType', originalName: 'heroImage.originalName', dimensions: 'heroImage.dimensions')]
    #[Ignore]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image(mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $heroImageFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    #[Ignore]
    private EmbeddedFile $heroImage;

    /** Legend or photo credit of the hero image */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Ignore]
    #[Assert\Length(max: 255)]
    private ?string $heroCaption = null;

    #[ORM\Column(name: 'is_featured', type: Types::BOOLEAN, options: ['default' => false])]
    #[Ignore]
    private bool $featured = false;

    /** Rank among the listed countries, lowest first; null leaves the country unranked */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Ignore]
    #[Assert\PositiveOrZero]
    private ?int $displayOrder = null;

    /**
     * Also what makes VichUploader store a new hero image: its listeners only run when a mapped
     * column changes, and the file property is not one (see setHeroImageFile()).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Ignore]
    #[Gedmo\Timestampable(on: 'update')]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->heroImage = new EmbeddedFile();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getKeyPrefix(): string
    {
        return self::KEY_PREFIX;
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return ObjectKey::internal(self::KEY_PREFIX, $this->getId());
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set id.
     */
    public function setId(string $id): self
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

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

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

    public function getCapital(): ?string
    {
        return $this->capital;
    }

    public function setCapital(string $capital): self
    {
        $this->capital = $capital;

        return $this;
    }

    public function getPostalCodeRegex(): ?string
    {
        return $this->postalCodeRegex;
    }

    public function setPostalCodeRegex(?string $postalCodeRegex): self
    {
        $this->postalCodeRegex = $postalCodeRegex;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getAtDisplayName(): ?string
    {
        return $this->atDisplayName;
    }

    public function setAtDisplayName(string $atDisplayName): self
    {
        $this->atDisplayName = $atDisplayName;

        return $this;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): self
    {
        $this->headline = $headline;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getHeroImageFile(): ?File
    {
        return $this->heroImageFile;
    }

    public function setHeroImageFile(?File $heroImageFile = null): self
    {
        $this->heroImageFile = $heroImageFile;

        if (null !== $heroImageFile) {
            // At least one mapped column must change, otherwise the Doctrine listeners are not
            // called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getHeroImage(): EmbeddedFile
    {
        return $this->heroImage;
    }

    public function setHeroImage(EmbeddedFile $heroImage): self
    {
        $this->heroImage = $heroImage;

        return $this;
    }

    public function hasHeroImage(): bool
    {
        return '' !== (string) $this->heroImage->getName();
    }

    public function getHeroCaption(): ?string
    {
        return $this->heroCaption;
    }

    public function setHeroCaption(?string $heroCaption): self
    {
        $this->heroCaption = $heroCaption;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
