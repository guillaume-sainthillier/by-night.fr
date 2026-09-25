<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Provider\TagAutocompleteProvider;
use App\Contracts\DependencyObjectInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Repository\TagRepository;
use App\Utils\CollationKey;
use App\Utils\ObjectKey;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Doctrine\ConditionalUpdate;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Tag',
    operations: [
        new GetCollection(
            uriTemplate: '/tags',
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
            ],
            openapi: new OpenApiOperation(
                summary: 'Search for tags',
                description: 'Returns a paginated list of tags (for categories and themes) matching the search query using Elasticsearch.',
            ),
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            paginationClientItemsPerPage: true,
            paginationMaximumItemsPerPage: 50,
            normalizationContext: ['groups' => ['tag:list']],
            name: 'api_tags',
            provider: TagAutocompleteProvider::class,
            parameters: [
                'q' => new QueryParameter(property: 'hydra:freetextQuery', required: true),
            ],
        ),
    ],
)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'tag_name_unique', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Un tag existe déjà avec ce nom')]
class Tag implements Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DependencyObjectInterface, ConditionalUpdate
{
    use EntityTimestampableTrait;

    /** The prefix of this entity's keys and of its DTO's, see ObjectKey */
    final public const string KEY_PREFIX = 'tag';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['elasticsearch:event:details', 'elasticsearch:tag:details', 'tag:list'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Groups(['elasticsearch:event:details', 'elasticsearch:tag:details', 'tag:list'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Gedmo\Slug(fields: ['name'], unique: false)]
    #[Groups(['elasticsearch:tag:details', 'tag:list'])]
    private ?string $slug = null;

    public bool $batchUpdate = false;

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function shouldBeUpdated(): bool
    {
        return !$this->batchUpdate;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getKeyPrefix(): string
    {
        return self::KEY_PREFIX;
    }

    public function getInternalId(): ?string
    {
        if (null === $this->id) {
            return null;
        }

        return ObjectKey::internal(self::KEY_PREFIX, $this->id);
    }

    public function getUniqueKey(): string
    {
        if (null === $this->name || '' === trim($this->name)) {
            return ObjectKey::transient(self::KEY_PREFIX, $this);
        }

        // The key of TagDto::getUniqueKey(): equal for the names the unique index on
        // tag.name holds equal, see CollationKey
        return ObjectKey::data(self::KEY_PREFIX, CollationKey::of(trim($this->name)));
    }
}
