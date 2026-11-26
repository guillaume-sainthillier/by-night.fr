<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Controller\AbstractController as BaseController;
use App\Repository\FollowRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The publishers and the venues a member follows ("Mes abonnements"), whose new events they hear of by e-mail: each
 * with its button, to stop following it.
 */
final class FollowController extends BaseController
{
    #[Route(path: '/abonnements', name: 'app_follow_list', methods: ['GET'])]
    public function index(FollowRepository $followRepository): Response
    {
        $user = $this->getAppUser();

        return $this->render('personal-space/follows.html.twig', [
            'follows' => $followRepository->findAllOfUser($user),
            'alerts' => $user->isFollowAlerts(),
        ]);
    }
}
