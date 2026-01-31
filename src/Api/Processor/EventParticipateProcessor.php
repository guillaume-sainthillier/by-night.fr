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
use App\Entity\UserEvent;
use App\Repository\EventRepository;
use App\Repository\UserEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<EventParticipateInput, EventParticipationOutput>
 */
final readonly class EventParticipateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private UserEventRepository $userEventRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EventParticipationOutput
    {
        $event = $context['request']->attributes->get('read_data');
        if (!$event instanceof Event) {
            throw new NotFoundHttpException('Not Found');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $userEvent = $this->userEventRepository->findOneBy([
            'user' => $user,
            'event' => $event,
        ]);

        if (null === $userEvent) {
            $userEvent = new UserEvent();
            $userEvent
                ->setUser($user)
                ->setEvent($event);
            $this->entityManager->persist($userEvent);
        }

        $userEvent->setGoing($data->like);
        $this->persistProcessor->process($event, $operation, $uriVariables, $context);

        // Update participation counts
        $participations = $this->eventRepository->getParticipationTrendsCount($event);
        $interests = $this->eventRepository->getInterestTrendsCount($event);
        $event->setParticipations($participations)->setInterests($interests);
        $this->persistProcessor->process($event, $operation, $uriVariables, $context);

        return new EventParticipationOutput(
            success: true,
            like: $data->like,
            likes: $participations + $interests,
        );
    }
}
