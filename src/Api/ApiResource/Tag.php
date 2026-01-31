<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Provider\TagProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Tag',
    operations: [
        new GetCollection(
            uriTemplate: '/tags',
            name: 'api_tags',
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
final readonly class Tag
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
    ) {
    }
}
