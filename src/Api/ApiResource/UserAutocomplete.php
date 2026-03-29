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
use App\Api\Provider\UserAutocompleteProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'UserAutocomplete',
    operations: [
        new GetCollection(
            uriTemplate: '/users/autocomplete',
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can search users.',
            openapi: new OpenApiOperation(
                summary: 'Search for users by username or email',
                description: 'Returns a list of users matching the search query for admin impersonation.',
            ),
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            name: 'api_users_autocomplete',
            provider: UserAutocompleteProvider::class,
            parameters: [
                'q' => new QueryParameter(
                    key: 'q',
                    schema: ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
                    description: 'Search query for user username or email',
                    required: true,
                    constraints: [
                        new Assert\Length(min: 2, max: 100),
                    ],
                ),
            ],
        ),
    ],
)]
final readonly class UserAutocomplete
{
    public function __construct(
        public string $username,
        public string $email,
    ) {
    }
}
