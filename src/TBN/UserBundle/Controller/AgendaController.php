<?php

namespace TBN\UserBundle\Controller;

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
        $em->persist($agenda);
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
        $em->persist($agenda);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    public function listAction()
    {
        $user = $this->getUser();
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
        if ($form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $agenda->setTrustedLocation(false);
            $agenda = $this->handleEvent($em, $agenda);
            
            if($agenda !== null) {
                $em->persist($agenda);
                $em->flush();

                $this->postSocial($agenda, $form);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
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

    public function newAction(Request $request)
    {
        $agenda     = new Agenda;
        $form = $this->createCreateForm($agenda);

        $form->handleRequest($request);
        if ($form->isValid())
        {
            $em = $this->getDoctrine()->getManager();

            $agenda->setTrustedLocation(false);
            $agenda = $this->handleEvent($em, $agenda);

            if($agenda !== null)
            {
                $user   = $this->getCurrentUser();
                $agenda->setUser($user)
                    ->setParticipations(1);
                $calendriers = $agenda->getCalendriers();
                $calendrier = (new Calendrier)->setAgenda($agenda)->setUser($user)->setParticipe(1);
                $calendriers->add($calendrier);

                $em->persist($agenda);
                $em->persist($calendrier);
                $em->flush();

                $this->postSocial($agenda, $form);
                $em->flush();

                $this->get('session')->getFlashBag()->add(
                    'success',
                    'Votre événement a bien été créé. Merci !'
                );
            }else {
                $this->get('session')->getFlashBag()->add(
                    'danger',
                    "Nous n'avons pas été en mesure de créér votre événement car il ne répondait pas aux critères de validation établis par notre plateforme."
                );
            }

            return $this->redirect($this->generateUrl('tbn_agenda_list'));
        }
        

        return $this->render('TBNUserBundle:Espace:new.html.twig', [
            "form"         => $form->createView(),
            "agenda"       => $agenda
       ]);
    }
    
    private function handleEvent($em, Agenda &$tmpAgenda)
    {
        $repoEvents = $em->getRepository('TBNAgendaBundle:Agenda');
        $repoPlaces = $em->getRepository('TBNAgendaBundle:Place');
        
        $eventHandler   = $this->get('tbn.event_handler');
        $siteManager    = $this->get('site_manager');
        $site           = $siteManager->getCurrentSite();
        
        $places = $repoPlaces->findBy(['site' => $site]);
        $agenda = $eventHandler->handle($places, $site, $tmpAgenda);
        
        $events = $repoEvents->findBy([
            'dateDebut' => $agenda->getDateDebut(),
            'dateFin' => $agenda->getDateFin(),
            'site' => $agenda->getSite()
        ]);
        
        return $eventHandler->handleEvent($events, $agenda);
    }

    protected function getServiceByName($service)
    {
        return $this->get("tbn.social.". strtolower($service === "facebook" ? "facebook_admin" : $service));
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
                "class" => "btn btn-danger btn-lg btn-block"
            ]
        ])
        ->getForm();
    }

    protected function createEditForm(Agenda $agenda)
    {
        return $this->createForm($this->getAgendaForm(), $agenda, [
            'action' => $this->generateUrl('tbn_agenda_edit', [
                "slug" => $agenda->getSlug()
            ]),
            'method' => 'POST'
        ])
        ->add("ajouter","submit",[
            "label" => "Enregistrer",
            "attr" => [
                "class" => "btn btn-primary btn-raised btn-lg btn-block"
            ]
        ]);
    }

    protected function getAgendaForm()
    {
        $user = $this->getUser();
        $repo = $this->getDoctrine()->getManager()->getRepository("TBNUserBundle:SiteInfo");

        $config = $this->container->getParameter('tbn_user.social');
        
        return new AgendaType($repo->findOneBy([]), $user, $config);
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
                "class" => "btn btn-primary btn-raised btn-lg btn-block"
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

    protected function checkIfOwner(Agenda $agenda)
    {
        $user_agenda = $agenda->getUser();
        $current_user = $this->getUser();

        if(!$current_user->hasRole("ROLE_ADMIN") && $user_agenda !== $current_user)
        {
            throw new AccessDeniedException("Vous n'êtes pas autorisé à modifier cet événement");
        }
    }
}
