<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Controller\AbstractController as BaseController;
use App\Manager\WidgetsManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WidgetsController extends BaseController
{
    #[Route(path: '/top/membres/{page<%patterns.page%>}', name: 'app_agenda_top_users', methods: ['GET'])]
    public function topUsers(WidgetsManager $widgetsManager, int $page = 1): Response
    {
        $topUsersData = $widgetsManager->getTopUsersData($page);

        return $this->render('location/hinclude/top-users.html.twig', [
            'topUsersData' => $topUsersData,
        ]);
    }
}
