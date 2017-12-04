<?php

namespace App\Controller\Admin;

use App\App\SocialManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use App\Entity\SiteInfo;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/info")
 */
class InfoController extends Controller
{
    /**
     * @Route("/", name="tbn_administration_info_index")
     */
    public function listAction(RouterInterface $router, SocialManager $socialManager)
    {
        $info = $socialManager->getSiteInfo();

        if (null === $info) {
            $info = new SiteInfo();
            $em   = $this->getDoctrine()->getManager();
            $em->persist($info);
            $em->flush();
        }

        return $this->redirectToRoute('tbn_administration_info_edit', [
            'id' => $info->getId(),
        ]);
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
