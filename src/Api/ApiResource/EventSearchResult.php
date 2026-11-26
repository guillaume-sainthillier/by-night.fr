<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Model\EventSearchInput;
use App\Api\Model\EventSummary;
use App\Api\Provider\EventSearchProvider;

/**
 * The events of a city the agenda lists, for the API (GET /api/events) and the AI assistants (MCP tool search_events):
 * the same input, the same answer.
 */
#[ApiResource(
    shortName: 'EventSearch',
    operations: [
        new Get(
            uriTemplate: '/events',
            stateless: true,
            cacheHeaders: ['public' => true, 'max_age' => 300, 'shared_max_age' => 300],
            openapi: new OpenApiOperation(summary: 'Search the events of a city', description: EventSearchResult::DESCRIPTION),
            name: 'api_events',
            input: EventSearchInput::class,
            provider: EventSearchProvider::class,
        ),
    ],
    mcp: [
        'search_events' => new McpTool(
            title: 'Search the events of a city',
            description: EventSearchResult::DESCRIPTION,
            annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            // The assistant reads the text: accents and slashes as they are
            normalizationContext: ['json_encode_options' => \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES],
            input: EventSearchInput::class,
            provider: EventSearchProvider::class,
        ),
    ],
)]
final readonly class EventSearchResult
{
    public const string DESCRIPTION = 'Lists the events (concerts, shows, exhibitions, festivals, parties, family outings…) of a city in France, Belgium, Switzerland or Monaco, from the By Night agenda, soonest first, or the most relevant first with a query. Each event has the URL of its page, its next session, its venue and its price. The texts are in French.';

    public function __construct(
        /** The city found */
        public ?string $city,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'The first day searched, YYYY-MM-DD'])]
        public string $from,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date', 'description' => 'The last day searched, YYYY-MM-DD: none for every event to come'])]
        public ?string $to,
        /** How many events the search finds, of which the first are listed */
        public int $total,
        /** @var list<EventSummary> */
        public array $events,
    ) {
    }
}
