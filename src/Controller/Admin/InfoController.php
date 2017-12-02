<?php

namespace AppBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use AppBundle\Entity\SiteInfo;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/info")
 */
class InfoController extends Controller
{
    /**
     * @Route("/", name="tbn_administration_info_index")
     */
    public function listAction()
    {
        $info = $this->get('app.social_manager')->getSiteInfo();

        if (null === $info) {
            $info = new SiteInfo();
            $em   = $this->getDoctrine()->getManager();
            $em->persist($info);
            $em->flush();
        }

        return $this->redirect($this->get('router')->generate('tbn_administration_info_edit', ['id' => $info->getId()]));
    }

    /**
     * @Route("/{id}", name="tbn_administration_info_edit", requirements={"id": "\d+"})
     */
    public function viewAction(SiteInfo $info)
    {
        return $this->render('Admin/Info/view.html.twig', [
            'info' => $info,
        ]);
    }
}
