<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Analytics\EventAudienceReport;
use App\Controller\AbstractController as BaseController;
use App\Dto\EventDto;
use App\DtoFactory\EventDtoFactory;
use App\Entity\Event;
use App\Enum\EventBulkAction;
use App\Enum\PersonalEventFilter;
use App\Form\Type\EventType;
use App\Manager\MemberEventBulkEditor;
use App\Manager\MemberEventPublisher;
use App\Repository\EventRepository;
use App\SearchRepository\DuplicateEventFinder;
use App\Security\Voter\EventVoter;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventController extends BaseController
{
    private const int EVENT_PER_PAGE = 50;

    #[Route(path: '/mes-soirees', name: 'app_event_list', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository, EventAudienceReport $audienceReport): Response
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
            'audience' => $audienceReport->lastDaysByEvent($events->getCurrentPageResults()),
        ]);
    }

    /**
     * Publishes, hides or deletes the member's events picked in their list, or those of an import's report, then
     * goes back to that page ("_target_path", a path of the personal space).
     */
    #[Route(path: '/mes-soirees/lot', name: 'app_event_bulk', methods: ['POST'])]
    public function bulk(Request $request, MemberEventBulkEditor $bulkEditor, TranslatorInterface $translator): Response
    {
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('event-bulk', $payload->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $target = $payload->getString('_target_path');
        // A local page of the personal space only: never another site
        $target = str_starts_with($target, '/espace-perso/') && !str_contains($target, '\\') ? $target : $this->generateUrl('app_event_list');

        $action = EventBulkAction::tryFrom($payload->getString('action'));
        $ids = array_map(intval(...), $payload->all('ids'));
        if (null === $action || [] === $ids) {
            $this->addFlash('warning', 'Choisissez au moins un événement, puis une action.');

            return $this->redirect($target);
        }

        $count = $bulkEditor->apply($this->getAppUser(), $ids, $action);
        $this->addFlash(0 === $count ? 'warning' : 'success', $translator->trans($action->getDoneMessage(), ['count' => $count], 'messages+intl-icu'));

        return $this->redirect($target);
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

    /**
     * The published events the member's new event likely repeats, asked by its form before it publishes it
     * (assets/js/utils/duplicates.js).
     */
    #[Route(path: '/nouvelle-soiree/doublons', name: 'app_event_new_duplicates', methods: ['POST'])]
    #[IsGranted(EventVoter::CREATE)]
    public function newDuplicates(Request $request, MemberEventPublisher $memberEventPublisher, DuplicateEventFinder $duplicateEventFinder): Response
    {
        return $this->renderDuplicates($request, $memberEventPublisher->createDto($this->getAppUser()), $duplicateEventFinder);
    }

    /**
     * The published events a draft likely repeats, before it goes online: from its form as filled in (POST), or as
     * saved, for the publish button of its preview (GET).
     */
    #[Route(path: '/{id<%patterns.id%>}/doublons', name: 'app_event_edit_duplicates', methods: ['GET', 'POST'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function editDuplicates(Request $request, Event $event, EventDtoFactory $eventDtoFactory, DuplicateEventFinder $duplicateEventFinder): Response
    {
        return $this->renderDuplicates($request, $eventDtoFactory->create($event), $duplicateEventFinder);
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

    /**
     * The visitors of the event's page and the clicks on its ticketing links, day by day (EventVisitSync).
     */
    #[Route(path: '/{id<%patterns.id%>}/statistiques', name: 'app_event_stats', methods: ['GET'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function stats(Event $event, EventAudienceReport $audienceReport): Response
    {
        return $this->render('personal-space/stats.html.twig', [
            'event' => $event,
            'audience' => $audienceReport->forEvent($event),
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
     * The list of the likely duplicates, shown in the confirmation dialog; nothing (204) when there is none. A posted
     * form is read as filled in, valid or not: its picture is left out by the page, its errors are the publication's
     * business.
     */
    private function renderDuplicates(Request $request, EventDto $dto, DuplicateEventFinder $duplicateEventFinder): Response
    {
        if ($request->isMethod('POST')) {
            $form = $this->createForm(EventType::class, $dto);
            $form->handleRequest($request);
            if (!$form->isSubmitted()) {
                throw new BadRequestHttpException('The event form was not sent.');
            }
        }

        $duplicates = $duplicateEventFinder->find($dto);
        if ([] === $duplicates) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        return $this->render('personal-space/_duplicates.html.twig', [
            'duplicates' => $duplicates,
        ]);
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
