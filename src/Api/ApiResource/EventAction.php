<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Api\Model\ContentRemovalRequestInput;
use App\Api\Model\ContentRemovalRequestOutput;
use App\Api\Model\EventParticipationInput;
use App\Api\Model\EventParticipationOutput;
use App\Api\Model\EventStateInput;
use App\Api\Model\EventStateOutput;
use App\Api\Processor\ContentRemovalRequestProcessor;
use App\Api\Processor\EventParticipationProcessor;
use App\Api\Processor\EventStateProcessor;
use App\Entity\Event;
use App\Security\Voter\EventVoter;

#[ApiResource(shortName: 'Event', operations: [
    new Patch(
        uriTemplate: '/events/{id}',
        // A merge of the fields sent: as JSON Merge Patch, or as plain JSON, which the site's AJAX helper sends
        inputFormats: ['json' => ['application/json', 'application/merge-patch+json']],
        security: "is_granted('" . EventVoter::EDIT . "', object)",
        securityMessage: 'You are not allowed to edit this event.',
        input: EventStateInput::class,
        output: EventStateOutput::class,
        name: 'api_event_update',
        processor: EventStateProcessor::class,
    ),
    new Put(
        uriTemplate: '/events/{id}/participation',
        security: "is_granted('ROLE_USER')",
        securityMessage: 'You must be logged in to participate.',
        input: EventParticipationInput::class,
        output: EventParticipationOutput::class,
        name: 'api_event_participation',
        processor: EventParticipationProcessor::class,
    ),
    new Post(
        uriTemplate: '/events/{id}/removal-requests',
        input: ContentRemovalRequestInput::class,
        output: ContentRemovalRequestOutput::class,
        name: 'api_event_removal_request',
        processor: ContentRemovalRequestProcessor::class,
    ),
], stateOptions: new Options(entityClass: Event::class))]
final class EventAction
{
}
