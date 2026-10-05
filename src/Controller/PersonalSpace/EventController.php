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
use App\DtoFactory\EventDtoFactory;
use App\Entity\Event;
use App\Enum\PersonalEventFilter;
use App\Form\Type\EventType;
use App\Manager\MemberEventPublisher;
use App\Repository\EventRepository;
use App\Security\Voter\EventVoter;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
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
        $q = $request->query->getString('q');
        // An unknown value (an old link, a hand-edited URL) lists every event rather than failing
        $filter = PersonalEventFilter::tryFrom($request->query->getString('status'));
        $events = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllByUserQueryBuilder($user, $q ?: null, $filter),
            $eventRepository,
            $page,
            self::EVENT_PER_PAGE,
            ['view' => 'events:personal-space:list'],
            fetchJoinCollection: false,
        );

        return $this->render('personal-space/index.html.twig', [
            'events' => $events,
            'q' => $q,
            'filter' => $filter,
            'counts' => $eventRepository->countByUserAndFilter($user),
        ]);
    }

    #[Route(path: '/nouvelle-soiree', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(Request $request, MemberEventPublisher $memberEventPublisher): Response
    {
        if (!$this->isGranted(EventVoter::CREATE)) {
            return $this->redirectToRoute('app_event_list');
        }

        $eventDto = $memberEventPublisher->createDto($this->getAppUser());
        $form = $this->createForm(EventType::class, $eventDto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $event = $memberEventPublisher->create($eventDto, $this->isSavedAsDraft($form), $form->get('comment')->getData());
            $this->addFlash(
                'success',
                $event->isDraft()
                    ? "Votre brouillon a bien été enregistré\u{a0}: il n'est pas visible sur le site."
                    : "Votre événement a bien été créé. Merci\u{a0}!"
            );

            return $this->redirectToRoute('app_event_list');
        }

        return $this->render('personal-space/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id<%patterns.id%>}', name: 'app_event_edit', methods: ['GET', 'POST'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function edit(Request $request, Event $event, EventDtoFactory $eventDtoFactory, MemberEventPublisher $memberEventPublisher): Response
    {
        $dto = $eventDtoFactory->create($event);
        $form = $this->createForm(EventType::class, $dto);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $event = $memberEventPublisher->update($dto, $this->isSavedAsDraft($form));
            $this->addFlash('success', $event->isDraft()
                ? "Votre brouillon a bien été enregistré\u{a0}: il n'est pas visible sur le site."
                : 'Votre événement a bien été modifié.');

            return $this->redirectToRoute('app_event_list');
        }

        return $this->render('personal-space/edit.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    #[Route(path: '{id<%patterns.id%>}', name: 'app_event_delete', methods: ['DELETE'])]
    #[IsGranted(EventVoter::DELETE, subject: 'event')]
    public function delete(Request $request, Event $event): Response
    {
        // The delete forms carry a token only their page can issue: no other site can post them
        if (!$this->isCsrfTokenValid('delete-event-' . $event->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em = $this->getEntityManager();
        $em->remove($event);
        $em->flush();
        $this->addFlash(
            'success',
            'Votre événement a bien été supprimé.'
        );

        return $this->redirectToRoute('app_event_list');
    }

    /**
     * Whether the event form was sent with its "Enregistrer en brouillon" button. Any other submission, the publish
     * button or Enter in a field (the browser then uses the form's first submit button, the publish one), publishes it.
     */
    private function isSavedAsDraft(FormInterface $form): bool
    {
        $saveDraft = $form->get('saveDraft');

        return $saveDraft instanceof ClickableInterface && $saveDraft->isClicked();
    }
}
