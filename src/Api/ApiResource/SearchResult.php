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
use App\Api\Provider\SearchSuggestionsProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Search',
    operations: [
        new GetCollection(
            uriTemplate: '/search',
            openapi: new OpenApiOperation(
                summary: 'Global search across events, cities, users and tags',
                description: 'Returns a paginated list of events, cities, users and tags matching the search query.',
            ),
            paginationEnabled: true,
            paginationItemsPerPage: 15,
            paginationClientItemsPerPage: true,
            paginationMaximumItemsPerPage: 50,
            name: 'api_search',
            provider: SearchProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    schema: ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    property: 'hydra:freetextQuery',
                    description: 'Search query for events, cities, users and tags',
                    required: true,
                    constraints: [
                        new Assert\Length(min: 1, max: 200),
                    ],
                ),
            ],
        ),
        new GetCollection(
            uriTemplate: '/search/suggestions',
            openapi: new OpenApiOperation(
                summary: 'What the global search shows before a word is typed',
                description: 'With a city: the categories of its events to come (shortDescription: their number), then its top events of the week. Without one, or an unknown one: the biggest cities of France, then its top events of the week.',
            ),
            paginationEnabled: false,
            // The same for every visitor of a city: a few minutes old is fine for suggestions
            cacheHeaders: ['public' => true, 'max_age' => 300, 'shared_max_age' => 300],
            name: 'api_search_suggestions',
            provider: SearchSuggestionsProvider::class,
            parameters: [
                'city' => new QueryParameter(
                    schema: ['type' => 'string', 'maxLength' => 255],
                    description: 'The slug of the city of the visitor, if the page knows one',
                    required: false,
                    constraints: [
                        new Assert\Length(max: 255),
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
        public ?array $highlightResult = null,
    ) {
    }
}
