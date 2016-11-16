<?php

namespace TBN\UserBundle\Controller;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Form\Type\AgendaType;
use TBN\AgendaBundle\Entity\Calendrier;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Vich\UploaderBundle\Mapping\PropertyMapping;


class AgendaController extends Controller
{

    public function annulerAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $annuler = $request->get('annuler', 'true');
        $modificationDerniereMinute = ($annuler === 'true' ? 'ANNULÉ' : null);

        $em = $this->getDoctrine()->getManager();
        $agenda->setModificationDerniereMinute($modificationDerniereMinute)->setDateModification(new \DateTime);
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    public function brouillonAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);

        $brouillon = $request->get('brouillon', 'true');
        $isBrouillon = ($brouillon === 'true');

        $em = $this->getDoctrine()->getManager();
        $agenda->setBrouillon($isBrouillon)->setDateModification(new \DateTime);
        $em->merge($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    public function listAction()
    {
        $user = $this->getUser();
        $soirees = $this->getRepo('TBNAgendaBundle:Agenda')->findBy([
            'user' => $user
        ], ['dateModification' => "DESC"]);

        $canSynchro = $user->hasRole('ROLE_FACEBOOK_LIST_EVENTS');

        return $this->render('TBNUserBundle:Espace:liste.html.twig', [
            "soirees" => $soirees,
            "canSynchro" => $canSynchro
        ]);
    }

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

    public function editAction(Request $request, Agenda $agenda)
    {
        $this->checkIfOwner($agenda);
        $form = $this->createEditForm($agenda);
        $formDelete = $this->createDeleteForm($agenda);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $agenda->setTrustedLocation(false);
            $agenda = $this->handleEvent($agenda);

            if ($agenda !== null) {
                $this->postSocial($agenda, $form);
                $em->merge($agenda);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été modifié'
                );
                return $this->redirect($this->generateUrl('tbn_agenda_list'));
            }

        }

        return $this->render('TBNUserBundle:Espace:edit.html.twig', [
            "form" => $form->createView(),
            "agenda" => $agenda,
            "form_delete" => $formDelete->createView()
        ]);
    }

    /**
     * @Security("has_role('ROLE_FACEBOOK_LIST_EVENTS')")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function importAction() {
        $importer = $this->get('tbn.social.facebook_list_events');
        $parser = $this->get('tbn.parser.abstracts.facebook');
        $handler = $this->get('tbn.doctrine_event_handler');
        $siteManager = $this->get('site_manager');

        $user = $this->getUser();
        $fb_events = $importer->getUserEvents($user);

        $handler->init($siteManager->getCurrentSite());

        $events = [];
        foreach($fb_events as $fb_event) {
            $array_event = $parser->getInfoAgenda($fb_event);
            $event = $parser->arrayToAgenda($array_event);

            $event
                ->setUser($user)
                ->setSite($siteManager->getCurrentSite())
                ->setTrustedLocation(false);
            $handler->handleEvent($event, false);
        }
        $handler->flush();

        $stats = $handler->getStats();
        $this->addImportMessage($stats);

        return $this->redirectToRoute('tbn_agenda_list');
    }

    protected function addImportMessage(array $stats) {
        if($stats['nbInserts'] > 0 || $stats['nbUpdates'] > 0) {
            $plurielInsert = $stats['nbInserts'] > 1 ? "s" : "";
            $plurielUpdate = $stats['nbUpdates'] > 1 ? "s" : "";
            $indicatifInsert = $stats['nbInserts'] == 1 ? "a": "ont";
            $indicatifUpdate = $stats['nbUpdates'] == 1 ? "a": "ont";

            if($stats['nbInserts'] > 0 && $stats['nbUpdates'] > 0) {
                $message = sprintf(
                    "<strong>%d</strong> événément%s %s été ajouté%s et <strong>%s</strong> %s été mis à jour sur la plateforme !",
                    $stats['nbInserts'],
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert,
                    $stats['nbUpdates'],
                    $indicatifUpdate
                );
            }elseif($stats['nbInserts'] > 0) {
                $message = sprintf(
                    "<strong>%d</strong> événément%s %s été ajouté%s sur By Night !",
                    $stats['nbInserts'],
                    $plurielInsert,
                    $indicatifInsert,
                    $plurielInsert
                );
            }elseif($stats['nbUpdates'] > 0) {
                $message = sprintf(
                    "<strong>%d</strong> événément%s %s été mis à jour sur By Night !",
                    $stats['nbUpdates'],
                    $plurielUpdate,
                    $indicatifUpdate
                );
            }

            $this->addFlash('success', $message);
        }elseif($stats['nbBlacklists'] > 0) {
            $plurielBlacklist = $stats['nbBlacklists'] > 1 ? "s" : "";
            $indicatifBlacklist = $stats['nbBlacklists'] == 1 ? "a": "ont";
            $conjugaisonBlacklist = $stats['nbBlacklists'] == 1 ? "e": "ent";

            $message = sprintf(
                "<strong>%d</strong> événément%s n'%s pas été ajouté%s sur By Night car il%s ne vérifi%s pas nos critères de validation.",
                $stats['nbBlacklists'],
                $plurielBlacklist,
                $indicatifBlacklist,
                $plurielBlacklist,
                $plurielBlacklist,
                $conjugaisonBlacklist
            );

            $this->addFlash('error', $message);
        }else {
            $message = "Aucun événément n'a été retrouvé sur votre compte.";

            $this->addFlash('info', $message);
        }
    }

    public function newAction(Request $request)
    {
        $agenda = new Agenda;
        $form = $this->createCreateForm($agenda);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $siteManager = $this->get('site_manager');
            $site = $siteManager->getCurrentSite();

            $user = $this->getUser();
            $agenda->setUser($user)
                ->setParticipations(1)
                ->setTrustedLocation(false)
                ->setSite($site);
            $agenda = $this->handleEvent($agenda);
            if ($agenda !== null) {
                $found = false;
                $calendriers = $agenda->getCalendriers();
                foreach ($calendriers as $calendrier) {
                    if ($calendrier->getUser()->getId() === $user->getId()) {
                        $found = true;
                    }
                }

                if (!$found) {
                    $calendrier = (new Calendrier)->setAgenda($agenda)->setUser($user)->setParticipe(1);
                    $em->merge($calendrier);
                    $em->flush();
                }

                $this->postSocial($agenda, $form);
                $em->merge($agenda);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été créé. Merci !'
                );

                return $this->redirect($this->generateUrl('tbn_agenda_list'));
            } else {
                $this->get('session')->getFlashBag()->add(
                    'danger',
                    "Nous n'avons pas été en mesure de créer votre événement car il ne répond pas aux critères de validation établis par notre plateforme."
                );
            }
        }


        return $this->render('TBNUserBundle:Espace:new.html.twig', [
            "form" => $form->createView(),
            "agenda" => $agenda
        ]);
    }

    private function handleEvent(Agenda &$tmpAgenda)
    {
        $eventHandler = $this->get('tbn.doctrine_event_handler');
        $eventHandler->init($tmpAgenda->getSite());

        $agenda = $eventHandler->handleEvent($tmpAgenda);

        return $agenda;
    }

    protected function getServiceByName($service)
    {
        return $this->get("tbn.social." . strtolower($service === "facebook" ? "facebook_admin" : $service));
    }

    protected function createDeleteForm(Agenda $agenda)
    {
        return $this->createFormBuilder($agenda, [
            'action' => $this->generateUrl('tbn_agenda_delete', [
                "id" => $agenda->getId()
            ]),
            'method' => 'DELETE'
        ])
            ->add("supprimer", SubmitType::class, [
                "label" => "Supprimer",
                "attr" => [
                    "class" => "btn btn-danger btn-raised btn-lg btn-block"
                ]
            ])
            ->getForm();
    }

    protected function createEditForm(Agenda $agenda)
    {
        $options = array_merge($this->getAgendaOptions(), [
            'action' => $this->generateUrl('tbn_agenda_edit', [
                "slug" => $agenda->getSlug()
            ]),
            'method' => 'POST'
        ]);

        return $this->createForm(AgendaType::class, $agenda, $options)
            ->add("ajouter", SubmitType::class, [
                "label" => "Enregistrer",
                "attr" => [
                    "class" => "btn btn-primary btn-raised btn-lg btn-block"
                ]
            ]);
    }

    protected function getAgendaOptions()
    {
        $user = $this->getUser();
        $siteInfo = $this->get('site_manager')->getSiteInfo();
        $config = $this->container->getParameter('tbn_user.social');

        return [
            'site_info' => $siteInfo,
            'user' => $user,
            'config' => $config
        ];
    }

    protected function createCreateForm(Agenda $agenda)
    {
        $options = array_merge($this->getAgendaOptions(), [
            'action' => $this->generateUrl('tbn_agenda_new'),
            'method' => 'POST'
        ]);

        return $this->createForm(AgendaType::class, $agenda, $options)
            ->add("ajouter", SubmitType::class, [
                "label" => "Enregistrer",
                "attr" => [
                    "class" => "btn btn-primary btn-raised btn-lg btn-block"
                ]
            ]);
    }

    protected function postSocial(Agenda $agenda, $form)
    {
        $config = $this->container->getParameter('tbn_user.social');
        foreach ($config as $social => $options) {
            $want_post = $form->get("share_" . $social)->getData();
            if ($want_post) {
                $service = $this->getServiceByName($social);
                $service->poster($agenda);
            }
        }
    }

    protected function checkIfOwner(Agenda $agenda)
    {
        $user_agenda = $agenda->getUser();
        $current_user = $this->getUser();

        if (!$current_user->hasRole("ROLE_ADMIN") && $user_agenda !== $current_user) {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à modifier cet événement");
        }
    }
}
