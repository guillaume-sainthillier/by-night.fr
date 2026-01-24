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
use App\Api\Provider\CityAutocompleteProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'City',
    operations: [
        new GetCollection(
            uriTemplate: '/cities',
            provider: CityAutocompleteProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    key: 'q',
                    description: 'Search query for city name autocomplete',
                    required: false,
                    schema: ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                    constraints: [
                        new Assert\Length(min: 1, max: 100),
                    ],
                ),
            ],
            cacheHeaders: [
                'max_age' => 31536000,
                'shared_max_age' => 31536000,
            ],
            openapi: new OpenApiOperation(
                summary: 'Search for cities by name',
                description: 'Returns a list of cities matching the search query for autocomplete purposes.',
            ),
        ),
    ],
)]
final class CityAutocomplete
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
    ) {
    }
}
