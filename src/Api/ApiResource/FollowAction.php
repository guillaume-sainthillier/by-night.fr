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
use App\Api\Model\FollowInput;
use App\Api\Model\FollowOutput;
use App\Api\Processor\FollowProcessor;
use App\Entity\Place;
use App\Entity\Publisher;

/**
 * A member follows a publisher or a venue ({following: true}), or stops following it: its new events are e-mailed to
 * them (App\Follow\FollowAlertSender).
 */
#[ApiResource(shortName: 'Publisher', operations: [
    new Put(
        uriTemplate: '/publishers/{id}/follow',
        security: "is_granted('ROLE_USER')",
        securityMessage: 'You must be logged in to follow a publisher.',
        input: FollowInput::class,
        output: FollowOutput::class,
        name: 'api_publisher_follow',
        processor: FollowProcessor::class,
    ),
], stateOptions: new Options(entityClass: Publisher::class))]
#[ApiResource(shortName: 'Place', operations: [
    new Put(
        uriTemplate: '/places/{id}/follow',
        security: "is_granted('ROLE_USER')",
        securityMessage: 'You must be logged in to follow a venue.',
        input: FollowInput::class,
        output: FollowOutput::class,
        name: 'api_place_follow',
        processor: FollowProcessor::class,
    ),
], stateOptions: new Options(entityClass: Place::class))]
final class FollowAction
{
}
