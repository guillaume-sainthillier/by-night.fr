<?php

namespace TBN\UserBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\UserBundle\Form\AgendaType;

class AgendaController extends Controller
{

    public function listAction()
    {
        $user = $this->getCurrentUser();
        $soirees = $user->getEvenements();

        return $this->render('TBNUserBundle:Espace:liste.html.twig', [
            "soirees"       => $soirees
        ]);
    }

    public function deleteAction(Agenda $agenda)
    {
        $this->checkIfOwner($agenda);
        $em = $this->getDoctrine()->getManager();
        $em->remove($agenda);
        $em->flush();

        $this->get('session')->getFlashBag()->add(
            'info',
            'Votre événement a bien été supprimé'
        );
        return $this->redirect($this->generateUrl('tbn_agenda_list'));
    }

    public function editAction(Agenda $agenda)
    {
        $this->checkIfOwner($agenda);
        $form = $this->createEditForm($agenda);
        $formDelete = $this->createDeleteForm($agenda);

        if ($this->getRequest()->isMethod('POST'))
        {
            $form->bind($this->getRequest());
            if ($form->isValid())
            {
                $em = $this->getDoctrine()->getManager();
                $em->persist($agenda);
                $em->flush();

                $this->postSocial($agenda, $form);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'info',
                    'Votre événement a bien été modifié'
                );
                return $this->redirect($this->generateUrl('tbn_agenda_list'));
            }
        }

        return $this->render('TBNUserBundle:Espace:edit.html.twig', [
            "form"         => $form->createView(),
            "agenda"       => $agenda,
            "form_delete"  => $formDelete->createView()
       ]);
    }

    public function newAction()
    {
        $agenda     = new Agenda;
        $form = $this->createCreateForm($agenda);

        if ($this->getRequest()->isMethod('POST'))
        {
            $form->bind($this->getRequest());
            if ($form->isValid())
            {
                $siteManager = $this->get('site_manager');
                $site = $siteManager->getCurrentSite();
                $em = $this->getDoctrine()->getManager();

                $agenda->setUser($this->getCurrentUser());
                $agenda->setSite($site);
                $em->persist($agenda);
                $em->flush();

                $this->postSocial($agenda, $form);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'info',
                    'Votre événement a bien été créé'
                );

                return $this->redirect($this->generateUrl('tbn_agenda_list'));
            }
        }

        return $this->render('TBNUserBundle:Espace:new.html.twig', [
            "form"         => $form->createView(),
            "agenda"       => $agenda
       ]);
    }

    protected function getServiceByName($service)
    {
        return $this->get("tbn.social.". strtolower($service));
    }

    protected function createDeleteForm(Agenda $agenda)
    {
        return $this->createFormBuilder($agenda, [
            'action' => $this->generateUrl('tbn_agenda_delete',[
                "id" => $agenda->getId()
            ]),
            'method' => 'DELETE'
        ])
        ->add("supprimer","submit",[
            "label" => "Supprimer",
            "attr" => [
                "class" => "btn btn-danger"
            ]
        ])
        ->getForm();
    }

    protected function createEditForm(Agenda $agenda)
    {
        return $this->createForm($this->getAgendaForm(),$agenda, [
            'action' => $this->generateUrl('tbn_agenda_edit', [
                "id" => $agenda->getId()
            ]),
            'method' => 'POST'
        ])
        ->add("ajouter","submit",[
            "label" => "Enregistrer",
            "attr" => [
                "class" => "btn btn-primary btn-lg btn-block"
            ]
        ])
        ->add("brouillon","submit",[
            "label" => "Brouillon",
            "attr" => [
                "class" => "btn btn-warning btn-lg btn-block"
            ]
        ]);
    }

    protected function getAgendaForm()
    {
        $user = $this->getCurrentUser();
        $repo = $this->getDoctrine()->getManager()->getRepository("TBNUserBundle:SiteInfo");

        $config = $this->container->getParameter('tbn_user.social');
        
        return new AgendaType($repo->findOneBy([]), $user->getInfo(), $config);
    }

    protected function createCreateForm(Agenda $agenda)
    {
        return $this->createForm($this->getAgendaForm(),$agenda,[
            'action' => $this->generateUrl('tbn_agenda_new'),
            'method' => 'POST'
        ])
        ->add("ajouter","submit",[
            "label" => "Enregistrer",
            "attr" => [
                "class" => "btn btn-primary btn-lg btn-block"
            ]
        ])
        ->add("brouillon","submit",[
            "label" => "Brouillon",
            "attr" => [
                "class" => "btn btn-warning btn-lg btn-block"
            ]
        ]);
    }

    protected function postSocial(Agenda $agenda, $form)
    {
        $config = $this->container->getParameter('tbn_user.social');
        foreach($config as $social => $options)
        {
            $want_post = $form->get("share_".$social)->getData();
            if($want_post)
            {
                $service = $this->getServiceByName($social);
                $service->poster($agenda);
            }
        }
    }

    protected function getCurrentUser()
    {
        return $user = $this->get('security.context')->getToken()->getUser();
    }

    protected function checkIfOwner(Agenda $agenda)
    {
        $user_agenda = $agenda->getUser();
        $current_user = $this->getCurrentUser();

        if($user_agenda !== $current_user)
        {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Vous n'êtes pas autorisé à modifier cet événement");
        }
    }
}
