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
use App\Repository\CityRepository;
use App\Utils\ObjectKey;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Override;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * The portal columns below live in the shared admin_zone table (single-table inheritance), so
 * Doctrine makes them nullable there: the ADM1/ADM2 rows never set them.
 */
#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: CityRepository::class)]
class City extends AdminZone implements InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    /** The prefix of this entity's keys and of its DTO's, see ObjectKey */
    final public const string KEY_PREFIX = 'city';

    #[ORM\ManyToOne(targetEntity: AdminZone::class)]
    #[Groups(['elasticsearch:city:details'])]
    protected ?AdminZone $parent = null;

    /** @var Collection<int, ZipCity> */
    #[ORM\OneToMany(targetEntity: ZipCity::class, mappedBy: 'parent', fetch: 'EXTRA_LAZY')]
    protected Collection $zipCities;

    /** Punchline of the city portal, under its name */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Ignore]
    #[Assert\Length(max: 255)]
    private ?string $headline = null;

    /** Editorial introduction of the city portal, in Markdown (rendered by the |markdown Twig filter) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Ignore]
    private ?string $description = null;

    #[Vich\UploadableField(mapping: 'city_image', fileNameProperty: 'heroImage.name', size: 'heroImage.size', mimeType: 'heroImage.mimeType', originalName: 'heroImage.originalName', dimensions: 'heroImage.dimensions')]
    #[Ignore]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image(mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $heroImageFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    #[Ignore]
    private EmbeddedFile $heroImage;

    #[ORM\Column(name: 'is_metropolis', type: Types::BOOLEAN, options: ['default' => false])]
    #[Ignore]
    private bool $metropolis = false;

    /** Rank among the listed cities, lowest first; null leaves the city unranked */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Ignore]
    #[Assert\PositiveOrZero]
    private ?int $displayOrder = null;

    /** Published events to come, recounted by app:events:count-upcoming (see UpcomingEventCounter) */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    #[Ignore]
    private int $upcomingEvents = 0;

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
        $this->zipCities = new ArrayCollection();
        $this->heroImage = new EmbeddedFile();
    }

    #[Groups(['elasticsearch:city:details'])]
    #[SerializedName('country')]
    #[Override]
    public function getCountry(): ?Country
    {
        return parent::getCountry();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getFullName();
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

    public function getFullName(): string
    {
        $parts = [];
        if (null !== $this->getParent()) {
            $parts[] = $this->getParent()->getName();
        }

        $parts[] = $this->getCountry()->getName();

        return \sprintf('%s (%s)', $this->getName(), implode(', ', $parts));
    }

    #[Groups(['elasticsearch:city:details'])]
    #[SerializedName('postalCodes')]
    public function getPostalCodes(): array
    {
        $postalCodes = [];
        foreach ($this->zipCities as $zipCity) {
            $postalCodes[] = $zipCity->getPostalCode();
        }

        return $postalCodes;
    }

    #[Override]
    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getParent(): ?AdminZone
    {
        return $this->parent;
    }

    public function setParent(?AdminZone $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, ZipCity>
     */
    public function getZipCities(): Collection
    {
        return $this->zipCities;
    }

    public function addZipCity(ZipCity $zipCity): static
    {
        if (!$this->zipCities->contains($zipCity)) {
            $this->zipCities[] = $zipCity;
            $zipCity->setParent($this);
        }

        return $this;
    }

    public function removeZipCity(ZipCity $zipCity): static
    {
        if ($this->zipCities->removeElement($zipCity)) {
            // set the owning side to null (unless already changed)
            if ($zipCity->getParent() === $this) {
                $zipCity->setParent(null);
            }
        }

        return $this;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): static
    {
        $this->headline = $headline;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getHeroImageFile(): ?File
    {
        return $this->heroImageFile;
    }

    public function setHeroImageFile(?File $heroImageFile = null): static
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

    public function setHeroImage(EmbeddedFile $heroImage): static
    {
        $this->heroImage = $heroImage;

        return $this;
    }

    public function hasHeroImage(): bool
    {
        return '' !== (string) $this->heroImage->getName();
    }

    public function isMetropolis(): bool
    {
        return $this->metropolis;
    }

    public function setMetropolis(bool $metropolis): static
    {
        $this->metropolis = $metropolis;

        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(?int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;

        return $this;
    }

    public function getUpcomingEvents(): int
    {
        return $this->upcomingEvents;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
