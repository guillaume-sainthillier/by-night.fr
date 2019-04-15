<?php

namespace App\Controller\EspacePerso;

use App\App\SocialManager;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
use App\Entity\Calendrier;
use App\Factory\EventFactory;
use App\Form\Type\AgendaType;
use App\Handler\DoctrineEventHandler;
use App\Handler\ExplorationHandler;
use App\Parser\Common\FaceBookParser;
use App\Social\FacebookListEvents;
use App\Social\SocialProvider;
use App\Validator\Constraints\EventConstraintValidator;
use DateTime;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EventController extends BaseController
{
    /**
     * @Route("/annuler/{slug}", name="app_agenda_annuler", requirements={"slug": ".+"})
     */
    public function annulerAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $annuler = $request->get('annuler', 'true');
        $modificationDerniereMinute = ('true' === $annuler ? 'ANNULÉ' : null);

        $em = $this->getDoctrine()->getManager();
        $agenda->setModificationDerniereMinute($modificationDerniereMinute)->setDateModification(new DateTime());
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/brouillon/{slug}", name="app_agenda_brouillon", requirements={"slug": ".+"})
     */
    public function brouillonAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $brouillon = $request->get('brouillon', 'true');
        $isBrouillon = ('true' === $brouillon);

        $em = $this->getDoctrine()->getManager();
        $agenda->setIsBrouillon($isBrouillon)->setDateModification(new DateTime());
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/mes-soirees", name="app_agenda_list")
     */
    public function indexAction()
    {
        $user = $this->getUser();
        $soirees = $this->getDoctrine()->getRepository(Agenda::class)->findAllByUser($user);

        $canSynchro = $user->hasRole('ROLE_FACEBOOK_LIST_EVENTS');

        return $this->render('EspacePerso/liste.html.twig', [
            'soirees' => $soirees,
            'canSynchro' => $canSynchro,
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="app_agenda_delete", requirements={"id": "\d+"})
     */
    public function deleteAction(Agenda $agenda)
    {
        $this->checkIfOwner($agenda);
        $em = $this->getDoctrine()->getManager();
        $em->remove($agenda);
        $em->flush();

        $this->addFlash(
            'success',
            'Votre événement a bien été supprimé'
        );

        return $this->redirect($this->generateUrl('app_agenda_list'));
    }

    /**
     * @Route("/corriger/{slug}", name="app_agenda_edit", requirements={"slug": ".+"})
     */
    public function editAction(Request $request, Agenda $agenda, EventConstraintValidator $validator)
    {
        $this->checkIfOwner($agenda);
        $form = $this->createEditForm($agenda);

        $validator->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', 'Votre événement a bien été modifié');

            //return $this->redirect($this->generateUrl('app_agenda_list'));
        }

        $formDelete = $this->createDeleteForm($agenda);

        return $this->render('EspacePerso/edit.html.twig', [
            'form' => $form->createView(),
            'agenda' => $agenda,
            'form_delete' => $formDelete->createView(),
        ]);
    }

    /**
     * @Route("/import", name="app_agenda_import_events")
     * @Security("has_role('ROLE_FACEBOOK_LIST_EVENTS')")
     */
    public function importAction(FacebookListEvents $importer, EventFactory $eventFactory, FaceBookParser $parser, DoctrineEventHandler $handler, ValidatorInterface $validator)
    {
        $user = $this->getUser();
        $fb_events = $importer->getUserEvents($user);

        $events = [];
        foreach ($fb_events as $fb_event) {
            $array_event = $parser->getInfoAgenda($fb_event);
            $event = $eventFactory->fromArray($array_event);
            $events[] = $event->setUser($user);
        }

        $events = $handler->handleMany($events);
        $this->addImportMessage($handler->getExplorationHandler());
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
                $this->addFlash('info', \sprintf(
                    "Informations sur l'événement <a href='https://facebook.com/events/%s/'>%s</a> : <ul>%s</ul>",
                    $event->getFacebookEventId(),
                    $event->getNom(),
                    \implode('', $errorsString)
                ));
            }
        }

        return $this->redirectToRoute('app_agenda_list');
    }

    protected function addImportMessage(ExplorationHandler $explorationHandler)
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
     * @Route("/espace-perso/nouvelle-soiree", name="app_agenda_new")
     */
    public function newAction(Request $request, EventConstraintValidator $validator)
    {
        $user = $this->getUser();
        $agenda = (new Agenda())
            ->setUser($user)
            ->setParticipations(1);

        $calendrier = (new Calendrier())
            ->setUser($user)
            ->setParticipe(true);
        $agenda->addCalendrier($calendrier);

        $form = $this->createCreateForm($agenda);
        $form->handleRequest($request);
        $validator->setUpdatabilityCkeck(false);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash(
                'success',
                'Votre événement a bien été créé. Merci !'
            );

            //return $this->redirect($this->generateUrl('app_agenda_list'));
        }

        return $this->render('EspacePerso/new.html.twig', [
            'form' => $form->createView()
        ]);
    }

    protected function createDeleteForm(Agenda $agenda)
    {
        return $this->createFormBuilder($agenda, [
            'action' => $this->generateUrl('app_agenda_delete', [
                'id' => $agenda->getId(),
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

    protected function createEditForm(Agenda $agenda)
    {
        return $this->createForm(AgendaType::class, $agenda)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    protected function getAgendaOptions(SocialManager $socialManager)
    {
        $user = $this->getUser();
        $siteInfo = $socialManager->getSiteInfo();

        return [
            'site_info' => $siteInfo,
            'user' => $user,
        ];
    }

    protected function createCreateForm(Agenda $agenda)
    {
        return $this->createForm(AgendaType::class, $agenda)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    protected function checkIfOwner(Agenda $agenda)
    {
        $user_agenda = $agenda->getUser();
        $current_user = $this->getUser();

        if (!$current_user->hasRole('ROLE_ADMIN') && $user_agenda !== $current_user) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à modifier cet événement");
        }
    }

    /**
     * @Route("/participer/{id}", name="app_user_participer", defaults={"participer": true, "interet": false})
     * @Route("/interet/{id}", name="app_user_interesser", defaults={"participer": false, "interet": true})
     */
    public function participerAction(Agenda $agenda, $participer, $interet)
    {
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository(Calendrier::class)->findOneBy(['user' => $user, 'agenda' => $agenda]);

        if (null === $calendrier) {
            $calendrier = new Calendrier();
            $calendrier->setUser($user)->setAgenda($agenda);
        }
        $calendrier->setParticipe($participer)->setInteret($interet);

        $em->persist($calendrier);
        $em->flush();

        $repo = $em->getRepository(Agenda::class);
        $participations = $repo->getCountTendancesParticipation($agenda);
        $interets = $repo->getCountTendancesInterets($agenda);

        $agenda->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'participer' => $participer,
            'interet' => $interet,
        ]);
    }
}
