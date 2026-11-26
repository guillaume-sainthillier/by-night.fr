<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Follow\FollowAlertUnsubscribeUrl;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FollowAlertsController extends AbstractController
{
    /**
     * The link of a follow alert: a page that asks for a confirmation, as the mail scanners open every link, and the
     * posts of that page and of the mailboxes' own button (RFC 8058), which stop the alerts at once. No login: the
     * signature of the link proves who it is for. The member keeps following: their pages still show it.
     */
    #[Route(path: '/abonnements/desinscription/{id<%patterns.id%>}', name: 'app_follow_alerts_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(Request $request, FollowAlertUnsubscribeUrl $unsubscribeUrl, UserRepository $userRepository, int $id): Response
    {
        $user = $userRepository->find($id);
        if (null === $user || !$unsubscribeUrl->isValid($request)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST') && $user->isFollowAlerts()) {
            $user->setFollowAlerts(false);
            $this->getEntityManager()->flush();
        }

        $response = $this->render('follow/unsubscribe.html.twig', [
            'user' => $user,
            'action' => $request->getRequestUri(),
        ]);
        // Only ever linked from an e-mail
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
