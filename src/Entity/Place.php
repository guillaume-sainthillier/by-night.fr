<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\App\Location;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;
use App\Reject\Reject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table]
#[ORM\Index(name: 'place_nom_idx', columns: ['nom'])]
#[ORM\Index(name: 'place_slug_idx', columns: ['slug'])]
#[ORM\Index(name: 'place_external_id_idx', columns: ['external_id'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity]
#[ExclusionPolicy('all')]
class Place implements ExternalIdentifiablesInterface, Stringable
{
    use EntityTimestampableTrait;
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Groups(['list_event'])]
    #[Expose]
    private ?int $id = null;
    /**
     * @var Collection<int, PlaceMetadata>
     */
    #[ORM\OneToMany(targetEntity: PlaceMetadata::class, mappedBy: 'place', cascade: ['persist', 'remove'])]
    private Collection $metadatas;
    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $externalId = null;
    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?string $ville = null;
    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?string $codePostal = null;
    #[ORM\Column(type: 'string', length: 256, nullable: true)]
    private ?string $facebookId = null;
    #[ORM\ManyToOne(targetEntity: City::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?City $city = null;
    private ?ZipCity $zipCity = null;
    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?Country $country = null;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $junk = null;
    private ?string $countryName = null;
    private ?Reject $reject = null;
    private ?Location $location = null;
    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?string $rue = null;
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?float $latitude = null;
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?float $longitude = null;
    #[Assert\NotBlank(message: 'Vous devez indiquer le lieu de votre événement')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['list_event'])]
    #[Expose]
    private ?string $nom = null;
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Gedmo\Slug(fields: ['nom'])]
    private ?string $slug = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $path = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    public function __construct()
    {
        $this->metadatas = new ArrayCollection();
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-return Collection<int, PlaceMetadata>
     */
    public function getExternalIdentifiables(): Collection
    {
        return $this->metadatas;
    }

    public function hasMetadata(ExternalIdentifiableInterface $externalIdentifiable): bool
    {
        foreach ($this->getExternalIdentifiables() as $metadata) {
            if ($metadata->getExternalId() === $externalIdentifiable->getExternalId() &&
                $metadata->getExternalOrigin() === $externalIdentifiable->getExternalOrigin()) {
                return true;
            }
        }

        return false;
    }

    public function getLocationSlug(): string|null
    {
        return $this->getLocation()->getSlug();
    }

    public function getLocation(): Location
    {
        if (null !== $this->location) {
            return $this->location;
        }

        $location = new Location();
        $location->setCity($this->city);
        $location->setCountry($this->country);

        return $this->location = $location;
    }

    public function getZipCity(): ?ZipCity
    {
        return $this->zipCity;
    }

    public function setZipCity(?ZipCity $zipCity): self
    {
        $this->zipCity = $zipCity;

        return $this;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function setCountryName(?string $countryName): self
    {
        $this->countryName = $countryName;

        return $this;
    }

    public function getReject(): ?Reject
    {
        return $this->reject;
    }

    public function setReject(Reject $reject = null): self
    {
        $this->reject = $reject;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (#%s)',
            $this->nom,
            $this->id
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getRue(): ?string
    {
        return $this->rue;
    }

    public function setRue(?string $rue): self
    {
        $this->rue = $rue;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): self
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getJunk(): ?bool
    {
        return $this->junk;
    }

    public function setJunk(?bool $junk): self
    {
        $this->junk = $junk;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * @return Collection<int, PlaceMetadata>
     */
    public function getMetadatas(): Collection
    {
        return $this->metadatas;
    }

    public function addMetadata(PlaceMetadata $metadata): self
    {
        if (!$this->metadatas->contains($metadata)) {
            $this->metadatas[] = $metadata;
            $metadata->setPlace($this);
        }

        return $this;
    }

    public function removeMetadata(PlaceMetadata $metadata): self
    {
        if ($this->metadatas->removeElement($metadata)) {
            // set the owning side to null (unless already changed)
            if ($metadata->getPlace() === $this) {
                $metadata->setPlace(null);
            }
        }

        return $this;
    }
}
