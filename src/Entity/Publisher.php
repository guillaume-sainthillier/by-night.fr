<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\PublisherNetwork;
use App\Picture\ImageFormats;
use App\Repository\PublisherRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

/**
 * Who publishes events on the site (a venue, a collective, a member in their own name): it owns its events, the members
 * who manage it edit them. A member who publishes gets one in their name on their first event
 * (App\Manager\MemberEventPublisher), the publishers of the events published before are named after their author.
 */
#[ORM\Entity(repositoryClass: PublisherRepository::class)]
#[Vich\Uploadable]
class Publisher implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    public const int DESCRIPTION_MAX_LENGTH = 2000;

    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    #[ORM\Column(type: Types::STRING, length: 180)]
    private ?string $name = null;

    /** Its page's URL (/organisateurs/{slug}): kept when it is renamed */
    #[ORM\Column(length: 128, unique: true)]
    #[Gedmo\Slug(fields: ['name'], updatable: false)]
    private ?string $slug = null;

    /** The member who manages it, the only one so far */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /** What it does, in its own words: the "À propos" of its page */
    #[Assert\Length(max: self::DESCRIPTION_MAX_LENGTH)]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $website = null;

    /**
     * Its pages on the social networks, by PublisherNetwork value: PublisherSocialLinksType checks each leads to its network.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $socialLinks = [];

    /** A square picture standing for it: on its page, its events and the cards */
    #[Vich\UploadableField(mapping: 'publisher_image', fileNameProperty: 'logo.name', size: 'logo.size', mimeType: 'logo.mimeType', originalName: 'logo.originalName', dimensions: 'logo.dimensions')]
    #[Assert\Image(maxSize: ImageFormats::MAX_SIZE, mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $logoFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $logo;

    /** A wide picture across the top of its page */
    #[Vich\UploadableField(mapping: 'publisher_image', fileNameProperty: 'cover.name', size: 'cover.size', mimeType: 'cover.mimeType', originalName: 'cover.originalName', dimensions: 'cover.dimensions')]
    #[Assert\Image(maxSize: ImageFormats::MAX_SIZE, mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $coverFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $cover;

    public function __construct()
    {
        $this->logo = new EmbeddedFile();
        $this->cover = new EmbeddedFile();
    }

    public function __toString(): string
    {
        return $this->name ?? '#' . ($this->id ?? '?');
    }

    /**
     * Whether the member manages it, and with it its events.
     */
    public function isManagedBy(User $user): bool
    {
        return null !== $user->getId() && $this->owner?->getId() === $user->getId();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null === $description || '' === trim($description) ? null : trim($description);

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    /**
     * @return array<string, string> by PublisherNetwork value
     */
    public function getSocialLinks(): array
    {
        return $this->socialLinks;
    }

    /**
     * @param array<string, string|null> $socialLinks by PublisherNetwork value; an empty one is dropped
     */
    public function setSocialLinks(array $socialLinks): self
    {
        $this->socialLinks = array_filter(
            array_map(static fn (?string $url): string => trim((string) $url), $socialLinks),
            static fn (string $url, string $network): bool => '' !== $url && null !== PublisherNetwork::tryFrom($network),
            \ARRAY_FILTER_USE_BOTH,
        );

        return $this;
    }

    /**
     * Its links, in the order of the networks.
     *
     * @return list<array{network: PublisherNetwork, url: string}>
     */
    public function getNetworkLinks(): array
    {
        $links = [];
        foreach (PublisherNetwork::cases() as $network) {
            if (isset($this->socialLinks[$network->value])) {
                $links[] = ['network' => $network, 'url' => $this->socialLinks[$network->value]];
            }
        }

        return $links;
    }

    public function getLogoFile(): ?File
    {
        return $this->logoFile;
    }

    public function setLogoFile(?File $logoFile = null): self
    {
        $this->logoFile = $logoFile;

        if (null !== $logoFile) {
            // A mapped field must change for the Doctrine listeners to store the file
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getLogo(): EmbeddedFile
    {
        return $this->logo;
    }

    public function setLogo(EmbeddedFile $logo): self
    {
        $this->logo = $logo;

        return $this;
    }

    public function hasLogo(): bool
    {
        return '' !== (string) $this->logo->getName();
    }

    public function getCoverFile(): ?File
    {
        return $this->coverFile;
    }

    public function setCoverFile(?File $coverFile = null): self
    {
        $this->coverFile = $coverFile;

        if (null !== $coverFile) {
            // A mapped field must change for the Doctrine listeners to store the file
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getCover(): EmbeddedFile
    {
        return $this->cover;
    }

    public function setCover(EmbeddedFile $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function hasCover(): bool
    {
        return '' !== (string) $this->cover->getName();
    }
}
