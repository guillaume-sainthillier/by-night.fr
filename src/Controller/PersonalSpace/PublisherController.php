<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\App\PublisherContext;
use App\Controller\AbstractController as BaseController;
use App\Entity\Event;
use App\Entity\EventExport;
use App\Entity\EventImport;
use App\Entity\Publisher;
use App\Enum\PublisherSpacePage;
use App\Form\Type\PublisherDeleteType;
use App\Form\Type\PublisherType;
use App\Manager\PublisherRemover;
use App\Repository\EventRepository;
use App\Repository\PublisherRepository;
use App\Security\Voter\EventVoter;
use App\Security\Voter\ExportVoter;
use App\Security\Voter\ImportVoter;
use App\Stats\PublisherCompletion;
use App\Stats\PublisherProfileProvider;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The member's organizer pages: one to edit (its page), one to create another, and the entries without a publisher
 * (the former URLs, the links of the site) that lead to the member's first one.
 */
final class PublisherController extends BaseController
{
    #[Route(path: '/organisateurs/{publisher<%patterns.slug%>}/page', name: 'app_publisher_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PublisherContext $publisherContext, PublisherProfileProvider $publisherProfileProvider, PublisherRepository $publisherRepository, EventRepository $eventRepository, PublisherRemover $publisherRemover): Response
    {
        $publisher = $publisherContext->get();
        $form = $this->createForm(PublisherType::class, $publisher);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getEntityManager()->flush();
            $this->addFlash('success', 'Votre page organisateur a bien été mise à jour.');

            return $this->redirectToRoute('app_publisher_edit');
        }

        $heirs = $this->getHeirs($publisher, $publisherRepository);
        $events = $eventRepository->count(['publisher' => $publisher]);
        $jobRunning = $publisherRemover->hasJobRunning($publisher);

        return $this->render('personal-space/publisher.html.twig', [
            'form' => $form,
            'publisher' => $publisher,
            'completion' => PublisherCompletion::of($publisher, $publisherProfileProvider->get($publisher)->stats),
            'events' => $events,
            'jobRunning' => $jobRunning,
            // None for the member's only page while it has events: they would have nowhere to go
            'deleteForm' => $jobRunning || (0 < $events && [] === $heirs) ? null : $this->createDeleteForm($publisher, $heirs, $events),
        ]);
    }

    /**
     * Deletes the publisher, its events moved to another of the member's pages or deleted with it, then goes to the
     * events of the page that took them, or to the member's first page.
     */
    #[Route(path: '/organisateurs/{publisher<%patterns.slug%>}/supprimer', name: 'app_publisher_delete', methods: ['POST'])]
    public function delete(Request $request, PublisherContext $publisherContext, PublisherRepository $publisherRepository, EventRepository $eventRepository, PublisherRemover $publisherRemover): Response
    {
        $publisher = $publisherContext->get();
        $heirs = $this->getHeirs($publisher, $publisherRepository);
        $events = $eventRepository->count(['publisher' => $publisher]);
        if (0 < $events && [] === $heirs) {
            throw $this->createAccessDeniedException('The only publisher of its owner keeps its events.');
        }

        if ($publisherRemover->hasJobRunning($publisher)) {
            $this->addFlash('warning', 'Un import ou un export de cette page est en cours : attendez qu’il se termine pour la supprimer.');

            return $this->redirectToRoute('app_publisher_edit', ['_fragment' => 'supprimer']);
        }

        $form = $this->createDeleteForm($publisher, $heirs, $events)->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }
            $this->addFlash('error', implode(' ', $messages) ?: 'La page n’a pas pu être supprimée.');

            return $this->redirectToRoute('app_publisher_edit', ['_fragment' => 'supprimer']);
        }

        $choice = $form->has('heir') ? (string) $form->get('heir')->getData() : PublisherDeleteType::DELETE_EVENTS;
        $heir = array_find($heirs, static fn (Publisher $candidate): bool => (string) $candidate->getId() === $choice);
        $name = $publisher->getName();
        $publisherRemover->remove($publisher, $heir);

        if (null !== $heir) {
            $this->addFlash('success', \sprintf('La page «%s%s%s» a été supprimée : ses événements sont maintenant ceux de «%s%s%s».', "\u{a0}", $name, "\u{a0}", "\u{a0}", $heir->getName(), "\u{a0}"));

            return $this->redirectToRoute('app_event_list', ['publisher' => $heir->getSlug()]);
        }

        $this->addFlash('success', \sprintf('La page «%s%s%s» a été supprimée.', "\u{a0}", $name, "\u{a0}"));

        return $this->redirectToRoute('app_personal_space_events');
    }

    /**
     * A new publisher the member runs: their first (named after them by default), or another one (a venue, a
     * collective). Then the page they were going to, of that publisher (?suite=).
     */
    #[Route(path: '/nouvelle-page-organisateur', name: 'app_publisher_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PublisherRepository $publisherRepository): Response
    {
        $user = $this->getAppUser();
        $first = null === $publisherRepository->findOneByOwner($user);
        $next = PublisherSpacePage::tryFrom($request->query->getString('suite')) ?? PublisherSpacePage::Page;
        $publisher = new Publisher()->setOwner($user)->setName($first ? $user->getUsername() : '');

        $form = $this->createFormBuilder($publisher)
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'help' => 'Le nom affiché sur votre page et sur vos événements : le vôtre, celui de votre lieu ou de votre collectif.',
            ])
            ->getForm()
            ->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getEntityManager();
            $em->persist($publisher);
            $em->flush();

            return $this->redirectToRoute($next->getRoute(), ['publisher' => $publisher->getSlug()]);
        }

        return $this->render('personal-space/publisher_new.html.twig', [
            'form' => $form,
            'first' => $first,
        ]);
    }

    /**
     * The other publishers of the publisher's owner: those its events may go to.
     *
     * @return list<Publisher>
     */
    private function getHeirs(Publisher $publisher, PublisherRepository $publisherRepository): array
    {
        $owner = $publisher->getOwner();

        return null === $owner ? [] : array_values(array_filter($publisherRepository->findByOwner($owner), static fn (Publisher $other): bool => $other->getId() !== $publisher->getId()));
    }

    /**
     * @param list<Publisher> $heirs
     */
    private function createDeleteForm(Publisher $publisher, array $heirs, int $events): FormInterface
    {
        return $this->createForm(PublisherDeleteType::class, null, [
            'publisher' => $publisher,
            'heirs' => $heirs,
            'events' => $events,
            'action' => $this->generateUrl('app_publisher_delete'),
        ]);
    }

    /**
     * The pages of the personal space without a publisher, as the site links to them and as they were before
     * publishers: those of the member's first one, or the creation of one when they have none.
     */
    #[Route(path: '/mes-soirees', name: 'app_personal_space_events', defaults: ['target' => 'evenements'], methods: ['GET'])]
    #[Route(path: '/nouvelle-soiree', name: 'app_personal_space_event_new', defaults: ['target' => 'nouvel-evenement'], methods: ['GET'])]
    #[Route(path: '/imports', name: 'app_personal_space_imports', defaults: ['target' => 'imports'], methods: ['GET'])]
    #[Route(path: '/exports', name: 'app_personal_space_exports', defaults: ['target' => 'exports'], methods: ['GET'])]
    #[Route(path: '/page-organisateur', name: 'app_personal_space_publisher', defaults: ['target' => 'page'], methods: ['GET'])]
    public function entry(Request $request, string $target, PublisherRepository $publisherRepository): Response
    {
        $target = PublisherSpacePage::from($target);
        $publisher = $publisherRepository->findOneByOwner($this->getAppUser());
        if (null === $publisher) {
            return $this->redirectToRoute('app_publisher_new', ['suite' => $target->value]);
        }

        // The filters and the search of a former link go along
        return $this->redirectToRoute($target->getRoute(), ['publisher' => $publisher->getSlug(), ...$request->query->all()]);
    }

    /**
     * An event's form, an import's report, an export, at their URL before publishers: on the pages of their publisher.
     */
    #[Route(path: '/{id<%patterns.id%>}', name: 'app_personal_space_event', methods: ['GET'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function legacyEvent(Event $event): Response
    {
        $publisher = $event->getPublisher() ?? throw $this->createNotFoundException('The event has no publisher.');

        return $this->redirectToRoute('app_event_edit', ['publisher' => $publisher->getSlug(), 'id' => $event->getId()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/{id<%patterns.id%>}/statistiques', name: 'app_personal_space_event_stats', methods: ['GET'])]
    #[IsGranted(EventVoter::EDIT, subject: 'event')]
    public function legacyEventStats(Request $request, Event $event): Response
    {
        $publisher = $event->getPublisher() ?? throw $this->createNotFoundException('The event has no publisher.');

        // Its period goes along
        return $this->redirectToRoute('app_event_stats', ['publisher' => $publisher->getSlug(), 'id' => $event->getId(), ...$request->query->all()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/imports/{id<%patterns.id%>}', name: 'app_personal_space_import', methods: ['GET'])]
    #[IsGranted(ImportVoter::VIEW, subject: 'import')]
    public function legacyImport(EventImport $import): Response
    {
        $publisher = $import->getPublisher() ?? throw $this->createNotFoundException('The import has no publisher.');

        return $this->redirectToRoute('app_event_import_show', ['publisher' => $publisher->getSlug(), 'id' => $import->getId()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: '/exports/{id<%patterns.id%>}', name: 'app_personal_space_export', methods: ['GET'])]
    #[IsGranted(ExportVoter::VIEW, subject: 'export')]
    public function legacyExport(EventExport $export): Response
    {
        $publisher = $export->getPublisher() ?? throw $this->createNotFoundException('The export has no publisher.');

        return $this->redirectToRoute('app_event_exports', ['publisher' => $publisher->getSlug(), '_fragment' => 'historique'], Response::HTTP_MOVED_PERMANENTLY);
    }
}
