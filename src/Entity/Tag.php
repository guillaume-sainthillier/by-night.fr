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
use App\Api\Provider\TagProvider;
use App\Repository\TagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Stringable;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Tag',
    operations: [
        new GetCollection(
            uriTemplate: '/tags',
            name: 'api_tags',
            normalizationContext: ['groups' => ['tag:read']],
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
            ],
            openapi: new OpenApiOperation(
                summary: 'Search for tags',
                description: 'Returns a list of tags (for categories and themes) matching the search query.',
            ),
            provider: TagProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    key: 'q',
                    schema: ['type' => 'string', 'maxLength' => 100],
                    description: 'Search query for tag autocomplete',
                    required: false,
                    constraints: [
                        new Assert\Length(max: 100),
                    ],
                ),
                'page' => new QueryParameter(
                    key: 'page',
                    schema: ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    description: 'Page number for pagination',
                    required: false,
                    constraints: [
                        new Assert\Positive(),
                    ],
                ),
                'itemsPerPage' => new QueryParameter(
                    key: 'itemsPerPage',
                    schema: ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    description: 'Number of items per page',
                    required: false,
                    constraints: [
                        new Assert\Positive(),
                        new Assert\LessThanOrEqual(100),
                    ],
                ),
            ],
        ),
    ],
)]
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\Index(name: 'tag_name_idx', columns: ['name'])]
class Tag implements Stringable
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['elasticsearch:event:details', 'tag:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Groups(['elasticsearch:event:details', 'tag:read'])]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    #[Gedmo\Slug(fields: ['name'])]
    #[Groups(['tag:read'])]
    private string $slug = '';

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
