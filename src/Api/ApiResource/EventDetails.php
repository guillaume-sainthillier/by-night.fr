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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use App\Api\Model\EventLookupInput;
use App\Api\Model\EventSession;
use App\Api\Model\EventSummary;
use App\Api\Provider\EventDetailsProvider;

/**
 * An event in detail, for the API (GET /api/events/{id}) and the AI assistants (MCP tool get_event).
 */
#[ApiResource(
    shortName: 'EventDetails',
    operations: [
        new Get(
            uriTemplate: '/events/{id}',
            requirements: ['id' => '\d+'],
            stateless: true,
            cacheHeaders: ['public' => true, 'max_age' => 300, 'shared_max_age' => 300],
            openapi: new OpenApiOperation(summary: 'Get an event', description: EventDetails::DESCRIPTION),
            name: 'api_event',
            provider: EventDetailsProvider::class,
        ),
    ],
    mcp: [
        'get_event' => new McpTool(
            title: 'Get an event',
            description: EventDetails::DESCRIPTION,
            annotations: ['readOnlyHint' => true, 'openWorldHint' => false],
            // The assistant reads the text: accents and slashes as they are
            normalizationContext: ['json_encode_options' => \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES],
            input: EventLookupInput::class,
            provider: EventDetailsProvider::class,
        ),
    ],
)]
final readonly class EventDetails
{
    public const string DESCRIPTION = 'The details of an event of the By Night agenda: its description, its sessions to come, its venue, its prices and the URL of its page. The texts are in French.';

    public function __construct(
        public EventSummary $event,
        /** Its text, without markup, cut after 1500 characters */
        public ?string $description,
        /** @var list<EventSession> the sessions to come, the first ten */
        public array $sessions,
        /** Its prices as the organizer writes them */
        public ?string $prices,
        /** What the organizer says of its cancellation or its postponement */
        public ?string $statusMessage,
    ) {
    }
}
