<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\App\SocialManager;
use App\Entity\AppOAuth;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/info")
 */
class InfoController extends AbstractController
{
    /**
     * @Route("/", name="app_administration_info_index", methods={"GET"})
     */
    public function list(SocialManager $socialManager): Response
    {
        $info = $socialManager->getAppOAuth();

        if (null === $info) {
            $info = new AppOAuth();
            $em = $this->getDoctrine()->getManager();
            $em->persist($info);
            $em->flush();
        }

        return $this->redirectToRoute('app_administration_info_edit', [
            'id' => $info->getId(),
        ]);
    }

    /**
     * @Route("/{id<%patterns.id%>}", name="app_administration_info_edit", methods={"GET"})
     */
    public function view(AppOAuth $appOAuth): Response
    {
        return $this->render('Admin/Social/view.html.twig', [
            'oAuth' => $appOAuth,
        ]);
    }
}
