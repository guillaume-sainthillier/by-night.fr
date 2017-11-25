<?php

namespace AppBundle\Controller\EspacePerso;

use AppBundle\Entity\User;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\ConstraintViolation;
use AppBundle\Controller\TBNController as Controller;
use AppBundle\Entity\Agenda;
use AppBundle\Handler\ExplorationHandler;
use AppBundle\Form\Type\AgendaType;
use AppBundle\Entity\Calendrier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;

class EventController extends Controller
{
    /**
     * @Route("/annuler/{slug}", name="tbn_agenda_annuler", requirements={"slug": ".+"})
     *
     * @param Request $request
     * @param Agenda  $agenda
     *
     * @return JsonResponse
     */
    public function annulerAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $annuler                    = $request->get('annuler', 'true');
        $modificationDerniereMinute = ('true' === $annuler ? 'ANNULÉ' : null);

        $em = $this->getDoctrine()->getManager();
        $agenda->setModificationDerniereMinute($modificationDerniereMinute)->setDateModification(new \DateTime());
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/brouillon/{slug}", name="tbn_agenda_brouillon", requirements={"slug": ".+"})
     *
     * @param Request $request
     * @param Agenda  $agenda
     *
     * @return JsonResponse
     */
    public function brouillonAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $brouillon   = $request->get('brouillon', 'true');
        $isBrouillon = ('true' === $brouillon);

        $em = $this->getDoctrine()->getManager();
        $agenda->setBrouillon($isBrouillon)->setDateModification(new \DateTime());
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/mes-soirees", name="tbn_agenda_list")
     */
    public function indexAction()
    {
        $user    = $this->getUser();
        $soirees = $this->getRepo('AppBundle:Agenda')->findAllByUser($user);

        $canSynchro = $user->hasRole('ROLE_FACEBOOK_LIST_EVENTS');

        return $this->render('EspacePerso/liste.html.twig', [
            'soirees'    => $soirees,
            'canSynchro' => $canSynchro,
        ]);
    }

    /**
     * @Route("/supprimer/{id}", name="tbn_agenda_delete", requirements={"id": "\d+"})
     *
     * @param Agenda $agenda
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Agenda $agenda)
    {
        $this->checkIfOwner($agenda);
        $em = $this->getDoctrine()->getManager();
        $em->remove($agenda);
        $em->flush();

        $this->get('session')->getFlashBag()->add(
            'success',
            'Votre événement a bien été supprimé'
        );

        return $this->redirect($this->generateUrl('tbn_agenda_list'));
    }

    /**
     * @Route("/corriger/{slug}", name="tbn_agenda_edit", requirements={"slug": ".+"})
     *
     * @param Request $request
     * @param Agenda  $agenda
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, Agenda $agenda)
    {
        $em = $this->getDoctrine()->getManager();
        $em->detach($agenda);

        $this->checkIfOwner($agenda);
        $form = $this->createEditForm($agenda);

        $this->get('tbn.event_validator')->setUpdatabilityCkeck(false);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $agenda->getPlace()->getCity()->getName();
            $this->postSocial($agenda, $form);

            try {
                $em->merge($agenda);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été modifié'
                );

                return $this->redirect($this->generateUrl('tbn_agenda_list'));
            } catch (FileException $exception) {
                $this->get('logger')->critical($exception);
                $this->addFlash('error', 'Un problème a eu lieu avec l\'envoi de votre pièce jointe');
            } catch (\Exception $exception) {
                $this->get('logger')->critical($exception);
                $this->addFlash('error', 'Un problème a eu lieu avec l\'enregistrement de votre événement');
            }
        }

        $formDelete = $this->createDeleteForm($agenda);

        return $this->render('EspacePerso/edit.html.twig', [
            'form'        => $form->createView(),
            'agenda'      => $agenda,
            'form_delete' => $formDelete->createView(),
        ]);
    }

    /**
     * @Route("/import", name="tbn_agenda_import_events")
     * @Security("has_role('ROLE_FACEBOOK_LIST_EVENTS')")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function importAction()
    {
        $importer     = $this->get('tbn.social.facebook_list_events');
        $eventFactory = $this->get('app.event_factory');
        $parser       = $this->get('tbn.parser.abstracts.facebook');
        $handler      = $this->get('tbn.doctrine_event_handler');

        $user      = $this->getUser();
        $fb_events = $importer->getUserEvents($user);

        $events = [];
        foreach ($fb_events as $fb_event) {
            $array_event = $parser->getInfoAgenda($fb_event);
            $event       = $eventFactory->fromArray($array_event);
            $events[]    = $event->setUser($user);
        }

        $events = $handler->handleMany($events);
        $this->addImportMessage($handler->getExplorationHandler());
        $validator = $this->get('validator');
        foreach ($events as $event) {
            /**
             * @var Agenda
             */
            $errors = $validator->validate($event);
            if ($errors->count() > 0) {
                $errorsString = [];
                foreach ($errors as $error) {
                    /*
                     * @var ConstraintViolation $error ;
                     */
                    $errorsString[] = \sprintf(
                        '<li>%s</li>',
                        $error->getMessage()
                    );
                }
                $this->addFlash('info', \sprintf(
                    "Informations sur l'événément <a href='https://facebook.com/events/%s/'>%s</a> : <ul>%s</ul>",
                    $event->getFacebookEventId(),
                    $event->getNom(),
                    \implode('', $errorsString)
                ));
            }
        }

        return $this->redirectToRoute('tbn_agenda_list');
    }

    protected function addImportMessage(ExplorationHandler $explorationHandler)
    {
        if ($explorationHandler->getNbInserts() > 0 || $explorationHandler->getNbUpdates() > 0) {
            $plurielInsert   = $explorationHandler->getNbInserts() > 1 ? 's' : '';
            $plurielUpdate   = $explorationHandler->getNbUpdates() > 1 ? 's' : '';
            $indicatifInsert = 1 == $explorationHandler->getNbInserts() ? 'a' : 'ont';
            $indicatifUpdate = 1 == $explorationHandler->getNbUpdates() ? 'a' : 'ont';
            $message         = null;
            if ($explorationHandler->getNbInserts() > 0 && $explorationHandler->getNbUpdates() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événément%s %s été ajouté%s et <strong>%s</strong> %s été mis à jour sur la plateforme !',
                    $explorationHandler->getNbInserts(),
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert,
                    $explorationHandler->getNbUpdates(),
                    $indicatifUpdate
                );
            } elseif ($explorationHandler->getNbInserts() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événément%s %s été ajouté%s sur By Night !',
                    $explorationHandler->getNbInserts(),
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert
                );
            } elseif ($explorationHandler->getNbUpdates() > 0) {
                $message = \sprintf(
                    '<strong>%d</strong> événément%s %s été mis à jour sur By Night !',
                    $explorationHandler->getNbUpdates(),
                    $plurielUpdate,
                    $indicatifUpdate
                );
            }

            $this->addFlash('success', $message);
        } elseif (0 === $explorationHandler->getNbBlackLists()) {
            $message = "Aucun événément n'a été retrouvé sur votre compte.";

            $this->addFlash('info', $message);
        }
    }

    /**
     * @Route("/espace-perso/nouvelle-soiree", name="tbn_agenda_new")
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $user   = $this->getUser();
        $agenda = (new Agenda())
            ->setUser($user)
            ->setParticipations(1);

        $form = $this->createCreateForm($agenda);
        $form->handleRequest($request);
        $this->get('tbn.event_validator')->setUpdatabilityCkeck(false);
        /**
         * @var Agenda
         */
        $agenda      = $form->getData();
        $isNewAgenda = null !== $agenda->getId();
        if ($form->isSubmitted() && $form->isValid()) {
            $em          = $this->getDoctrine()->getManager();
            $agenda      = $em->merge($agenda);
            $found       = false;
            $calendriers = $agenda->getCalendriers();
            foreach ($calendriers as $calendrier) {
                if ($calendrier->getUser()->getId() === $user->getId()) {
                    $found = true;
                }
            }

            if (!$found) {
                $calendrier = (new Calendrier())->setAgenda($agenda)->setUser($user)->setParticipe(1);
                $em->merge($calendrier);
            }

            $this->postSocial($agenda, $form);
            $em->flush();

            if ($isNewAgenda) {
                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été mis à jour'
                );
            } else {
                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été créé. Merci !'
                );
            }

            return $this->redirect($this->generateUrl('tbn_agenda_list'));
        }

        return $this->render('EspacePerso/new.html.twig', [
            'form'   => $form->createView(),
            'agenda' => $agenda,
        ]);
    }

    protected function getServiceByName($service)
    {
        return $this->get('tbn.social.' . \strtolower('facebook' === $service ? 'facebook_admin' : $service));
    }

    protected function createDeleteForm(Agenda $agenda)
    {
        return $this->createFormBuilder($agenda, [
            'action' => $this->generateUrl('tbn_agenda_delete', [
                'id' => $agenda->getId(),
            ]),
            'method' => 'DELETE',
        ])
            ->add('supprimer', SubmitType::class, [
                'label' => 'Supprimer',
                'attr'  => [
                    'class' => 'btn btn-danger btn-raised btn-lg btn-block',
                ],
            ])
            ->getForm();
    }

    protected function createEditForm(Agenda $agenda)
    {
        $options = \array_merge($this->getAgendaOptions(), [
            'action' => $this->generateUrl('tbn_agenda_edit', [
                'slug' => $agenda->getSlug(),
            ]),
            'method' => 'POST',
        ]);

        return $this->createForm(AgendaType::class, $agenda, $options)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr'  => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    protected function getAgendaOptions()
    {
        $user     = $this->getUser();
        $siteInfo = $this->get('site_manager')->getSiteInfo();

        return [
            'site_info' => $siteInfo,
            'user'      => $user,
        ];
    }

    /**
     * @param Agenda $agenda
     *
     * @return \Symfony\Component\Form\FormInterface
     */
    protected function createCreateForm(Agenda $agenda)
    {
        $options = \array_merge($this->getAgendaOptions(), [
            'action' => $this->generateUrl('tbn_agenda_new'),
            'method' => 'POST',
        ]);

        return $this->createForm(AgendaType::class, $agenda, $options)
            ->add('ajouter', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr'  => [
                    'class' => 'btn btn-primary btn-raised btn-lg btn-block',
                ],
            ]);
    }

    protected function postSocial(Agenda $agenda, $form)
    {
        $services = [
            'facebook' => ['nom' => 'Facebook'],
            'twitter'  => ['nom' => 'Twitter'],
        ];
        foreach ($services as $id => $infos) {
            $want_post = $form->get('share_' . $id)->getData();
            if ($want_post) {
                $service = $this->getServiceByName($id);
                $service->poster($agenda);
            }
        }
    }

    protected function checkIfOwner(Agenda $agenda)
    {
        $user_agenda  = $agenda->getUser();
        $current_user = $this->getUser();

        if (!$current_user->hasRole('ROLE_ADMIN') && $user_agenda !== $current_user) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à modifier cet événement");
        }
    }

    private function updateFBEvent(Agenda $agenda, User $user, Calendrier $calendrier)
    {
        if ($agenda->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookAccessToken()) {
            $key   = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
            $cache = $this->get('memory_cache');
            $api   = $this->get('tbn.social.facebook_admin');
            $api->updateEventStatut(
                $agenda->getFacebookEventId(),
                $user->getInfo()->getFacebookAccessToken(),
                $calendrier->getParticipe()
            );

            $datas = [
                'participer' => $calendrier->getParticipe(),
                'interet'    => $calendrier->getInteret(),
            ];

            $cache->save($key, $datas);
        }
    }

    /**
     * @Route("/participer/{id}", name="tbn_user_participer", defaults={"participer": true, "interet": false})
     * @Route("/interet/{id}", name="tbn_user_interesser", defaults={"participer": false, "interet": true})
     */
    public function participerAction(Agenda $agenda, $participer, $interet)
    {
        $user = $this->getUser();

        $em         = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository('AppBundle:Calendrier')->findOneBy(['user' => $user, 'agenda' => $agenda]);

        if (null === $calendrier) {
            $calendrier = new Calendrier();
            $calendrier->setUser($user)->setAgenda($agenda);
        }
        $calendrier->setParticipe($participer)->setInteret($interet);
        $this->updateFBEvent($agenda, $user, $calendrier);

        $em->persist($calendrier);
        $em->flush();

        $repo           = $em->getRepository('AppBundle:Agenda');
        $participations = $repo->getCountTendancesParticipation($agenda);
        $interets       = $repo->getCountTendancesInterets($agenda);

        $agenda->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            'success'    => true,
            'participer' => $participer,
            'interet'    => $interet,
        ]);
    }
}