<?php

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Entity\SiteInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/info")
 */
class InfoController extends AbstractController
{
    /**
     * @Route("/", name="tbn_administration_info_index")
     *
     * @param RouterInterface $router
     * @param SocialManager   $socialManager
     *
     * @return RedirectResponse
     */
    public function listAction(SocialManager $socialManager)
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
     *
     * @param SiteInfo $info
     *
     * @return Response
     */
    public function viewAction(SiteInfo $info)
    {
        return $this->render('Admin/Info/view.html.twig', [
            'info' => $info,
        ]);
    }
}
