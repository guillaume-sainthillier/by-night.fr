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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Doctrine\ConditionalUpdate;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    shortName: 'Tag',
    operations: [
        new GetCollection(
            uriTemplate: '/tags',
            name: 'api_tags',
            normalizationContext: ['groups' => ['tag:list']],
            paginationEnabled: true,
            paginationItemsPerPage: 20,
            paginationClientItemsPerPage: true,
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
            ],
            openapi: new OpenApiOperation(
                summary: 'Search for tags',
                description: 'Returns a paginated list of tags (for categories and themes) matching the search query using Elasticsearch.',
            ),
            provider: TagAutocompleteProvider::class,
            parameters: [
                'q' => new QueryParameter(property: 'hydra:freetextQuery', required: true),
            ],
        ),
    ],
)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\Index(name: 'tag_name_idx', columns: ['name'])]
class Tag implements Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface, DependencyObjectInterface, ConditionalUpdate
{
    use EntityTimestampableTrait;

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
        return $this->name;
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
        return 'tag';
    }

    public function getInternalId(): ?string
    {
        if (null === $this->id) {
            return null;
        }

        return \sprintf(
            '%s-id-%d',
            $this->getKeyPrefix(),
            $this->id
        );
    }

    public function getUniqueKey(): string
    {
        if (null === $this->name || '' === trim($this->name)) {
            return \sprintf(
                '%s-spl-%s',
                $this->getKeyPrefix(),
                spl_object_id($this)
            );
        }

        return \sprintf(
            '%s-data-%s',
            $this->getKeyPrefix(),
            mb_strtolower(trim($this->name))
        );
    }
}
