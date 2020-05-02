<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Entity\SiteInfo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/info")
 */
class InfoController extends AbstractController
{
    /**
     * @Route("/", name="app_administration_info_index")
     *
     * @return RedirectResponse
     */
    public function list(SocialManager $socialManager)
    {
        $info = $socialManager->getSiteInfo();

        if (null === $info) {
            $info = new SiteInfo();
            $em = $this->getDoctrine()->getManager();
            $em->persist($info);
            $em->flush();
        }

        return $this->redirectToRoute('app_administration_info_edit', [
            'id' => $info->getId(),
        ]);
    }

    /**
     * @Route("/{id}", name="app_administration_info_edit", requirements={"id": "\d+"})
     *
     * @return Response
     */
    public function view(SiteInfo $info)
    {
        return $this->render('Admin/Social/view.html.twig', [
            'info' => $info,
        ]);
    }
}
