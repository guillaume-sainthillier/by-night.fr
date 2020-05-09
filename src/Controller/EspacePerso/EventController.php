<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\EspacePerso;

use App\Controller\TBNController as BaseController;
use App\Entity\UserEvent;
use App\Entity\Comment;
use App\Entity\Event;
use App\Form\Type\EventType;
use App\Repository\UserEventRepository;
use App\Repository\EventRepository;
use App\Validator\Constraints\EventConstraintValidator;
use DateTime;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends BaseController
{
    private const EVENT_PER_PAGE = 50;

    /**
     * @Route("/mes-soirees", name="app_event_list", methods={"GET"})
     */
    public function index(Request $request, PaginatorInterface $paginator, EventRepository $eventRepository): Response
    {
        $user = $this->getUser();

        $page = (int) $request->query->get('page', 1);
        $query = $eventRepository->findAllByUser($user);
        $events = $paginator->paginate($query, $page, self::EVENT_PER_PAGE);

        return $this->render('EspacePerso/liste.html.twig', [
            'events' => $events,
        ]);
    }

    /**
     * @Route("/nouvelle-soiree", name="app_event_new", methods={"GET", "POST"})
     */
    public function new(Request $request, EventConstraintValidator $validator): Response
    {
        $user = $this->getUser();
        $event = (new Event())
            ->setUser($user)
            ->setParticipations(1);

        $userEvent = (new UserEvent())
            ->setUser($user)
            ->setParticipe(true);
        $event->addUserEvent($userEvent);

        $form = $this->createForm(EventType::class, $event);
        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            if ($form->get('comment')->getData()) {
                $event = $form->getData();
                $comment = new Comment();
                $comment
                    ->setCommentaire($form->get('comment')->getData())
                    ->setEvent($event)
                    ->setUser($user);
                $em->persist($comment);
            }
            $em->flush();
            $this->addFlash(
                'success',
                'Votre événement a bien été créé. Merci !'
            );

            return $this->redirect($this->generateUrl('app_event_list'));
        }

        return $this->render('EspacePerso/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id<%patterns.id%>}", name="app_event_edit", methods={"GET", "POST"})
     * @IsGranted("edit", subject="event")
     */
    public function edit(Request $request, Event $event, EventConstraintValidator $validator): Response
    {
        if ($event->getExternalId()) {
            $event->setExternalUpdatedAt(new DateTime());
        }

        $form = $this->createForm(EventType::class, $event);
        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Votre événement a bien été modifié');

            return $this->redirect($this->generateUrl('app_event_list'));
        }

        return $this->render('EspacePerso/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    /**
     * @Route("{id<%patterns.id%>}", name="app_event_delete", methods={"DELETE"})
     * @IsGranted("delete", subject="event")
     */
    public function delete(Event $event): Response
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($event);
        $em->flush();

        $this->addFlash(
            'success',
            'Votre événement a bien été supprimé'
        );

        return $this->redirect($this->generateUrl('app_event_list'));
    }

    /**
     * @Route("{id<%patterns.id%>}/annuler", name="app_event_annuler", methods={"POST"})
     * @IsGranted("edit", subject="event")
     */
    public function annuler(Request $request, Event $event): Response
    {
        $annuler = $request->request->get('annuler', 'true');
        $modificationDerniereMinute = ('true' === $annuler ? 'ANNULÉ' : null);

        $event->setModificationDerniereMinute($modificationDerniereMinute);
        $em = $this->getDoctrine()->getManager();
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("{id<%patterns.id%>}/brouillon", name="app_event_brouillon", methods={"POST"})
     * @IsGranted("edit", subject="event")
     */
    public function brouillon(Request $request, Event $event): Response
    {
        $brouillon = $request->request->get('brouillon', 'true');
        $isBrouillon = 'true' === $brouillon;

        $event->setBrouillon($isBrouillon);
        $em = $this->getDoctrine()->getManager();
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/{id<%patterns.id%>}/participer", name="app_user_like", methods={"POST"})
     */
    public function like(Request $request, Event $event, EventRepository $eventRepository, UserEventRepository $userEventRepository): Response
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $userEvent = $userEventRepository->findOneBy(['user' => $user, 'event' => $event]);

        if (null === $userEvent) {
            $userEvent = new UserEvent();
            $userEvent
                ->setUser($user)
                ->setEvent($event);
            $em->persist($userEvent);
        }
        $isLike = 'true' === $request->request->get('like', 'true');
        $userEvent->setParticipe($isLike);
        $em->flush();

        $participations = $eventRepository->getCountTendancesParticipation($event);
        $interets = $eventRepository->getCountTendancesInterets($event);

        $event->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'like' => $isLike,
            'likes' => $participations + $interets,
        ]);
    }
}
