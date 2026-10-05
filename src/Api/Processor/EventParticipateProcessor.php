<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Model\EventParticipateInput;
use App\Api\Model\EventParticipationOutput;
use App\Entity\Event;
use App\Entity\User;
use App\Manager\EventParticipationManager;
use App\Security\Voter\EventVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<EventParticipateInput, EventParticipationOutput>
 */
final readonly class EventParticipateProcessor implements ProcessorInterface
{
    public function __construct(
        private EventParticipationManager $eventParticipationManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventParticipationOutput
    {
        $event = $context['request']->attributes->get('read_data');
        // A draft is only for those who can see it: for anyone else, it does not exist
        if (!$event instanceof Event || !$this->security->isGranted(EventVoter::VIEW, $event)) {
            throw new NotFoundHttpException('Not Found');
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $this->eventParticipationManager->participate($user, $event, $data->like);

        return new EventParticipationOutput(
            success: true,
            like: $data->like,
            likes: $event->getParticipations() + $event->getInterests(),
        );
    }
}
