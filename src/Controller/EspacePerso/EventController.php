<?php

namespace App\Controller\EspacePerso;

use App\Controller\TBNController as BaseController;
use App\Entity\Calendrier;
use App\Entity\Comment;
use App\Entity\Event;
use App\Form\Type\EventType;
use App\Validator\Constraints\EventConstraintValidator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends BaseController
{
    private const EVENT_PER_PAGE = 50;

    /**
     * @Route("/annuler/{slug}", name="app_event_annuler", requirements={"slug": ".+"})
     * @IsGranted("edit", subject="event")
     */
    public function annulerAction(Request $request, Event $event)
    {
        $annuler = $request->get('annuler', 'true');
        $modificationDerniereMinute = ('true' === $annuler ? 'ANNULÉ' : null);

        $em = $this->getDoctrine()->getManager();
        $event->setModificationDerniereMinute($modificationDerniereMinute);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/brouillon/{slug}", name="app_event_brouillon", requirements={"slug": ".+"})
     * @IsGranted("edit", subject="event")
     */
    public function brouillonAction(Request $request, Event $event)
    {
        $brouillon = $request->get('brouillon', 'true');
        $isBrouillon = 'true' === $brouillon;

        $em = $this->getDoctrine()->getManager();
        $event->setBrouillon($isBrouillon);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/mes-soirees", name="app_event_list")
     */
    public function indexAction(Request $request)
    {
        $user = $this->getUser();

        $page = (int)$request->query->get('page', 1);
        $events = $this->getDoctrine()->getRepository(Event::class)->findAllByUser($user, $page, self::EVENT_PER_PAGE);

        return $this->render('EspacePerso/liste.html.twig', [
            'events' => $events,
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="app_event_delete", requirements={"id": "\d+"})
     * @IsGranted("edit", subject="event")
     */
    public function deleteAction(Event $event)
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
     * @Route("/corriger/{slug}", name="app_event_edit", requirements={"slug": ".+"})
     * @IsGranted("edit", subject="event")
     */
    public function editAction(Request $request, Event $event, EventConstraintValidator $validator)
    {
        if ($event->getExternalId()) {
            $event->setExternalUpdatedAt(new \DateTime());
        }

        $form = $this->createForm(EventType::class, $event);
        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Votre événement a bien été modifié');

            return $this->redirect($this->generateUrl('app_event_list'));
        }

        $formDelete = $this->createDeleteForm($event);

        return $this->render('EspacePerso/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
            'form_delete' => $formDelete->createView(),
        ]);
    }

    /**
     * @Route("/nouvelle-soiree", name="app_event_new")
     */
    public function newAction(Request $request, EventConstraintValidator $validator)
    {
        $user = $this->getUser();
        $event = (new Event())
            ->setUser($user)
            ->setParticipations(1);

        $calendrier = (new Calendrier())
            ->setUser($user)
            ->setParticipe(true);
        $event->addCalendrier($calendrier);

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

    protected function createDeleteForm(Event $event)
    {
        return $this->createFormBuilder($event, [
            'action' => $this->generateUrl('app_event_delete', [
                'id' => $event->getId(),
            ]),
            'method' => 'DELETE',
        ])
            ->getForm();
    }

    /**
     * @Route("/participer/{id}", name="app_user_like", defaults={"participer": true, "interet": false})
     */
    public function like(Request $request, Event $event)
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository(Calendrier::class)->findOneBy(['user' => $user, 'event' => $event]);

        if (null === $calendrier) {
            $calendrier = new Calendrier();
            $calendrier->setUser($user)->setEvent($event);
            $em->persist($calendrier);
        }
        $isLike = $request->request->get('like') === 'true';
        $calendrier->setParticipe($isLike);

        $em->flush();

        $repo = $em->getRepository(Event::class);
        $participations = $repo->getCountTendancesParticipation($event);
        $interets = $repo->getCountTendancesInterets($event);

        $event->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'like' => $isLike,
            'likes' => $participations + $interets,
        ]);
    }
}
