<?php

namespace TBN\AdministrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use TBN\MainBundle\Entity\Site;
use TBN\MainBundle\Form\Type\SiteType;

class SiteController extends Controller
{
    public function listAction()
    {
        $repo = $this->getDoctrine()->getRepository('TBNMainBundle:Site');
        $sites = $repo->findAll();

        return $this->render('TBNAdministrationBundle:Site:list.html.twig', [
            'sites' => $sites,
        ]);
    }

    public function newAction(Request $request)
    {
        $site = new Site();

        $form = $this->createForm(SiteType::class, $site, [
            'action' => $this->generateUrl('tbn_administration_site_new'),
            'method' => 'POST',
        ])
            ->add('ajouter', SubmitType::class, [
                'label' => 'ajouter',
                'attr'  => [
                    'class' => 'btn btn-primary',
                ],
            ]);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($site);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'info',
                'Le site <b>'.$site->getNom().'</b> a bien été ajouté'
            );

            return $this->redirect($this->generateUrl('tbn_administration_site_index'));
        }

        return $this->render('TBNAdministrationBundle:Site:new.html.twig', [
            'form'      => $form->createView(),
            'edit_site' => $site,
        ]);
    }

    public function editAction(Request $request, Site $site)
    {
        $form = $this->createForm(SiteType::class, $site, [
            'action' => $this->generateUrl('tbn_administration_site_edit', [
                'id' => $site->getId(),
            ]),
            'method' => 'POST',
        ])
            ->add('modifier', SubmitType::class, [
                'label' => 'Modifier',
                'attr'  => [
                    'class' => 'btn btn-primary',
                ],
            ]);

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($site);
            $em->flush();

            $this->get('session')->getFlashBag()->add(
                'info',
                'Le site <b>'.$site->getNom().'</b> a bien été modifié'
            );

            return $this->redirect($this->generateUrl('tbn_administration_site_index'));
        }

        return $this->render('TBNAdministrationBundle:Site:edit.html.twig', [
            'form'      => $form->createView(),
            'edit_site' => $site,
        ]);
    }
}
