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
use App\Api\Provider\SearchProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Search',
    operations: [
        new GetCollection(
            uriTemplate: '/search',
            name: 'api_search',
            provider: SearchProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    property: 'hydra:freetextQuery',
                    description: 'Search query for events, cities and users',
                    required: true,
                    schema: ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    constraints: [
                        new Assert\Length(min: 1, max: 200),
                    ],
                ),
            ],
            openapi: new OpenApiOperation(
                summary: 'Global search across events, cities and users',
                description: 'Returns a list of events, cities and users matching the search query.',
            ),
        ),
    ],
)]
final class SearchResult
{
    /**
     * @param array<string, array<string, string>>|null $highlightResult
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $category,
        public readonly string $label,
        public readonly string $shortDescription,
        public readonly ?string $description,
        public readonly string $url,
        public readonly string $icon,
        public readonly ?array $highlightResult = null,
    ) {
    }
}
