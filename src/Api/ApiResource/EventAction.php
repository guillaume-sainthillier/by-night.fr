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
use ApiPlatform\Metadata\Put;
use App\Api\Model\EventActionOutput;
use App\Api\Model\EventCancelInput;
use App\Api\Model\EventDraftInput;
use App\Api\Model\EventParticipateInput;
use App\Api\Model\EventParticipationOutput;
use App\Api\Processor\EventCancelProcessor;
use App\Api\Processor\EventDraftProcessor;
use App\Api\Processor\EventParticipateProcessor;
use App\Entity\Event;
use App\Security\Voter\EventVoter;

#[ApiResource(shortName: 'Event', operations: [
    new Put(
        uriTemplate: '/events/{id}/cancel',
        security: "is_granted('" . EventVoter::EDIT . "', object)",
        securityMessage: 'You are not allowed to edit this event.',
        input: EventCancelInput::class,
        output: EventActionOutput::class,
        name: 'api_event_cancel',
        processor: EventCancelProcessor::class,
    ),
    new Put(
        uriTemplate: '/events/{id}/draft',
        security: "is_granted('" . EventVoter::EDIT . "', object)",
        securityMessage: 'You are not allowed to edit this event.',
        input: EventDraftInput::class,
        output: EventActionOutput::class,
        name: 'api_event_draft',
        processor: EventDraftProcessor::class,
    ),
    new Put(
        uriTemplate: '/events/{id}/participer',
        security: "is_granted('ROLE_USER')",
        securityMessage: 'You must be logged in to participate.',
        input: EventParticipateInput::class,
        output: EventParticipationOutput::class,
        name: 'api_event_participer',
        processor: EventParticipateProcessor::class,
    ),
], stateOptions: new Options(entityClass: Event::class))]
final class EventAction
{
}
