<?php

namespace App\Controller\EspacePerso;

use App\App\SocialManager;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\Calendrier;
use App\Factory\EventFactory;
use App\Form\Type\EventType;
use App\Handler\DoctrineEventHandler;
use App\Handler\ExplorationHandler;
use App\Parser\Common\FaceBookParser;
use App\Social\FacebookListEvents;
use App\Validator\Constraints\EventConstraintValidator;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventController extends BaseController
{
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
        $em->merge($event);
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
        $event->setIsBrouillon($isBrouillon);
        $em->merge($event);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/mes-soirees", name="app_event_list")
     */
    public function indexAction()
    {
        $user = $this->getUser();
        $events = $this->getDoctrine()->getRepository(Event::class)->findAllByUser($user);

        $canSynchro = $user->hasRole('ROLE_FACEBOOK_LIST_EVENTS');

        return $this->render('EspacePerso/liste.html.twig', [
            'events' => $events,
            'canSynchro' => $canSynchro,
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
        $form = $this->createEditForm($event);

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
     * @Route("/import", name="app_event_import_events")
     * @IsGranted("ROLE_FACEBOOK_LIST_EVENTS")
     */
    public function importAction(FacebookListEvents $importer, ExplorationHandler $explorationHandler, EventFactory $eventFactory, FaceBookParser $parser, DoctrineEventHandler $handler, ValidatorInterface $validator)
    {
        $user = $this->getUser();
        $fb_events = $importer->getUserEvents($user);

        $events = [];
        foreach ($fb_events as $fb_event) {
            $array_event = $parser->getInfoEvent($fb_event);
            $event = $eventFactory->fromArray($array_event);
            $events[] = $event->setUser($user);
        }

        $explorationHandler->start();
        $events = $handler->handleMany($events);
        $explorationHandler->stop();

        $this->addImportMessage($explorationHandler);
        foreach ($events as $event) {
            $errors = $validator->validate($event);
            if ($errors->count() > 0) {
                $errorsString = [];
                foreach ($errors as $error) {
                    /** @var ConstraintViolation $error */
                    $errorsString[] = \sprintf(
                        '<li>%s</li>',
                        $error->getMessage()
                    );
                }
                $this->addFlash('warning', \sprintf(
                    "Informations sur l'événement <a href='https://facebook.com/events/%s/'>%s</a> : <ul>%s</ul>",
                    $event->getFacebookEventId(),
                    $event->getNom(),
                    \implode('', $errorsString)
                ));
            }
        }

        return $this->redirectToRoute('app_event_list');
    }

    private function addImportMessage(ExplorationHandler $explorationHandler)
    {
        if ($explorationHandler->getNbInserts() > 0 || $explorationHandler->getNbUpdates() > 0) {
            $plurielInsert = $explorationHandler->getNbInserts() > 1 ? 's' : '';
            $plurielUpdate = $explorationHandler->getNbUpdates() > 1 ? 's' : '';
            $indicatifInsert = 1 == $explorationHandler->getNbInserts() ? 'a' : 'ont';
            $indicatifUpdate = 1 == $explorationHandler->getNbUpdates() ? 'a' : 'ont';
            $message = null;
            if ($explorationHandler->getNbInserts() > 0 && $explorationHandler->getNbUpdates() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événement%s %s été ajouté%s et <strong>%s</strong> %s été mis à jour sur la plateforme !',
                    $explorationHandler->getNbInserts(),
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert,
                    $explorationHandler->getNbUpdates(),
                    $indicatifUpdate
                );
            } elseif ($explorationHandler->getNbInserts() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événement%s %s été ajouté%s sur By Night !',
                    $explorationHandler->getNbInserts(),
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert
                );
            } elseif ($explorationHandler->getNbUpdates() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événement%s %s été mis à jour sur By Night !',
                    $explorationHandler->getNbUpdates(),
                    $plurielUpdate,
                    $indicatifUpdate
                );
            }

            $this->addFlash('success', $message);
        } elseif (0 === $explorationHandler->getNbBlackLists()) {
            $message = "Aucun événement n'a été retrouvé sur votre compte.";

            $this->addFlash('info', $message);
        }
    }

    /**
     * @Route("/espace-perso/nouvelle-soiree", name="app_event_new")
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

        $form = $this->createCreateForm($event);
        $form->handleRequest($request);
        $validator->setUpdatabilityCkeck(false);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash(
                'success',
                'Votre événement a bien été créé. Merci !'
            );

            return $this->redirect($this->generateUrl('app_event_list'));
        }

        return $this->render('EspacePerso/new.html.twig', [
            'form' => $form->createView()
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
            ->add('supprimer', SubmitType::class, [
                'label' => 'Supprimer',
                'attr' => [
                    'class' => 'btn btn-danger btn-raised btn-lg btn-block',
                ],
            ])
            ->getForm();
    }

    protected function createEditForm(Event $event)
    {
        return $this->createForm(EventType::class, $event)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    protected function getEventOptions(SocialManager $socialManager)
    {
        $user = $this->getUser();
        $siteInfo = $socialManager->getSiteInfo();

        return [
            'site_info' => $siteInfo,
            'user' => $user,
        ];
    }

    protected function createCreateForm(Event $event)
    {
        return $this->createForm(EventType::class, $event)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    /**
     * @Route("/participer/{id}", name="app_user_participer", defaults={"participer": true, "interet": false})
     * @Route("/interet/{id}", name="app_user_interesser", defaults={"participer": false, "interet": true})
     */
    public function participerAction(Event $event, $participer, $interet)
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository(Calendrier::class)->findOneBy(['user' => $user, 'event' => $event]);

        if (null === $calendrier) {
            $calendrier = new Calendrier();
            $calendrier->setUser($user)->setEvent($event);
        }
        $calendrier->setParticipe($participer)->setInteret($interet);

        $em->persist($calendrier);
        $em->flush();

        $repo = $em->getRepository(Event::class);
        $participations = $repo->getCountTendancesParticipation($event);
        $interets = $repo->getCountTendancesInterets($event);

        $event->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'participer' => $participer,
            'interet' => $interet,
        ]);
    }
}
