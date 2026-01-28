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
use ApiPlatform\Metadata\Post;
use App\Api\Model\FeedbackInput;
use App\Api\Model\FeedbackOutput;
use App\Api\Processor\FeedbackProcessor;

#[ApiResource(operations: [
    new Post(
        uriTemplate: '/feedback',
        security: "is_granted('ROLE_USER')",
        securityMessage: 'Vous devez être connecté pour envoyer un feedback.',
        input: FeedbackInput::class,
        output: FeedbackOutput::class,
        name: 'api_feedback',
        processor: FeedbackProcessor::class,
    ),
])]
final class Feedback
{
}
