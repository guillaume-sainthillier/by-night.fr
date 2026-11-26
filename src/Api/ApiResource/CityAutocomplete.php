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
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Model\CitySearchInput;
use App\Api\Model\CitySearchResult;
use App\Api\Provider\CityAutocompleteProvider;

#[ApiResource(
    shortName: 'City',
    operations: [
        new GetCollection(
            uriTemplate: '/cities',
            // The same for every visitor: never opens a session
            stateless: true,
            // A day: the events to come are counted again each night
            cacheHeaders: [
                'max_age' => 86400,
                'shared_max_age' => 86400,
            ],
            openapi: new OpenApiOperation(
                summary: 'Search for cities by name',
                description: 'Returns a paginated list of cities matching the search query for autocomplete purposes.',
            ),
            paginationEnabled: true,
            paginationItemsPerPage: 7,
            paginationMaximumItemsPerPage: 50,
            paginationClientItemsPerPage: true,
            name: 'api_cities',
            input: CitySearchInput::class,
            provider: CityAutocompleteProvider::class,
        ),
    ],
    mcp: [
        // The same cities as GET /api/cities, in an object: a tool answers one
        'find_cities' => new McpTool(
            title: 'Find a city',
            description: 'Finds the cities of the By Night agenda whose name or postal code matches, the most populated first, with the slug search_events takes and how many events are to come there.',
            annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            // The assistant reads the text: accents and slashes as they are
            normalizationContext: ['json_encode_options' => \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES],
            paginationItemsPerPage: 10,
            input: CitySearchInput::class,
            output: CitySearchResult::class,
            provider: CityAutocompleteProvider::class,
        ),
    ],
)]
final readonly class CityAutocomplete
{
    public function __construct(
        /** What search_events and the agenda's URLs name the city by */
        public string $slug,
        /** Its name, its region and its country: "Toulouse (Occitanie, France)" */
        public string $name,
        /** How many events are to come there */
        public int $upcomingEvents = 0,
    ) {
    }
}
