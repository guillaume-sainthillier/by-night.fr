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

        return $this->render('TBNUserBundle:Espace:liste.html.twig', [
            "soirees" => $soirees
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
