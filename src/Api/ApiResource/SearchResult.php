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
            openapi: new OpenApiOperation(
                summary: 'Global search across events, cities and users',
                description: 'Returns a list of events, cities and users matching the search query.',
            ),
            name: 'api_search',
            provider: SearchProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    schema: ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    property: 'hydra:freetextQuery',
                    description: 'Search query for events, cities and users',
                    required: true,
                    constraints: [
                        new Assert\Length(min: 1, max: 200),
                    ],
                ),
            ],
        ),
    ],
)]
final readonly class SearchResult
{
    /**
     * @param array<string, array<string, string>>|null $highlightResult
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $category,
        public string $label,
        public string $shortDescription,
        public ?string $description,
        public string $url,
        public string $icon,
        public ?array $highlightResult = null,
    ) {
    }
}
