<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Controller\AbstractController as BaseController;
use App\Dto\EventDto;
use App\Dto\UserDto;
use App\DtoFactory\EventDtoFactory;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Form\Type\EventType;
use App\Repository\EventRepository;
use App\Security\Voter\EventVoter;
use App\Validator\Constraints\EventConstraintValidator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventController extends BaseController
{
    private const int EVENT_PER_PAGE = 50;

    #[Route(path: '/mes-soirees', name: 'app_event_list', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $user = $this->getAppUser();
        $page = $request->query->getInt('page', 1);
        $events = $this->createQueryBuilderPaginator(
            $eventRepository->findAllByUserQueryBuilder($user),
            $page,
            self::EVENT_PER_PAGE
        );

        return $this->render('personal-space/index.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route(path: '/nouvelle-soiree', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EventConstraintValidator $validator, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted(EventVoter::CREATE)) {
            return $this->redirectToRoute('app_event_list');
        }

        $userDto = new UserDto();
        $userDto->entityId = $this->getAppUser()->getId();

        $eventDto = new EventDto();
        $eventDto->user = $userDto;

        $form = $this->createForm(EventType::class, $eventDto);
        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $entityManager->getReference(User::class, $eventDto->user->entityId);
            $event = $entityManager->getReference(Event::class, $eventDto->entityId);
            $event->setParticipations(1);

            $userEvent = new UserEvent()
                ->setUser($user)
                ->setGoing(true);
            $event->addUserEvent($userEvent);
            $em = $this->getEntityManager();

            $em->persist($event);
            if ($form->get('comment')->getData()) {
                $comment = new Comment();
                $comment
                    ->setComment($form->get('comment')->getData())
                    ->setEvent($event)
                    ->setUser($user);
                $em->persist($comment);
            }

            $em->flush();
            $this->addFlash(
                'success',
                'Votre événement a bien été créé. Merci !'
            );

            return $this->redirectToRoute('app_event_list');
        }

        return $this->render('personal-space/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id<%patterns.id%>}', name: 'app_event_edit', methods: ['GET', 'POST'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function edit(Request $request, Event $event, EventConstraintValidator $validator, EventDtoFactory $eventDtoFactory): Response
    {
        if ($event->getExternalId()) {
            $event->setExternalUpdatedAt(new DateTimeImmutable());
        }

        $dto = $eventDtoFactory->create($event);
        $form = $this->createForm(EventType::class, $dto);
        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getEntityManager()->flush();
            $this->addFlash('success', 'Votre événement a bien été modifié');

            return $this->redirectToRoute('app_event_list');
        }

        return $this->render('personal-space/edit.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    #[Route(path: '{id<%patterns.id%>}', name: 'app_event_delete', methods: ['DELETE'])]
    #[IsGranted(EventVoter::DELETE, subject: 'event')]
    public function delete(Event $event): Response
    {
        $em = $this->getEntityManager();
        $em->remove($event);
        $em->flush();
        $this->addFlash(
            'success',
            'Votre événement a bien été supprimé'
        );

        return $this->redirectToRoute('app_event_list');
    }
}
