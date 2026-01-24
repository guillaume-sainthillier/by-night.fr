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

#[ApiResource(
    shortName: 'Event',
    stateOptions: new Options(entityClass: Event::class),
    operations: [
        new Put(
            uriTemplate: '/events/{id}/cancel',
            name: 'api_event_cancel',
            input: EventCancelInput::class,
            output: EventActionOutput::class,
            processor: EventCancelProcessor::class,
            security: "is_granted('" . EventVoter::EDIT . "', object)",
            securityMessage: 'You are not allowed to edit this event.',
        ),
        new Put(
            uriTemplate: '/events/{id}/draft',
            name: 'api_event_draft',
            input: EventDraftInput::class,
            output: EventActionOutput::class,
            processor: EventDraftProcessor::class,
            security: "is_granted('" . EventVoter::EDIT . "', object)",
            securityMessage: 'You are not allowed to edit this event.',
        ),
        new Put(
            uriTemplate: '/events/{id}/participer',
            name: 'api_event_participer',
            input: EventParticipateInput::class,
            output: EventParticipationOutput::class,
            processor: EventParticipateProcessor::class,
            security: "is_granted('ROLE_USER')",
            securityMessage: 'You must be logged in to participate.',
        ),
    ],
)]
final class EventAction
{
}
