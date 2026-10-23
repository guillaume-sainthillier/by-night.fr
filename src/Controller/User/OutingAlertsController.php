<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Controller\AbstractController as BaseController;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Turns off the alerts of a member's outing changes, from the link of an alert, logged in or not: the link is signed
 * (MailerManager::sendOutingChangedEmail()). A mail app does it in one click, posting "List-Unsubscribe=One-Click"
 * (RFC 8058); the link of the e-mail asks first, a link checker or a preview opening it would otherwise do it. The
 * profile turns them on again.
 */
final class OutingAlertsController extends BaseController
{
    #[Route(path: '/alertes-sorties/{id<%patterns.id%>}/desabonnement', name: 'app_outing_alerts_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(Request $request, User $user, UriSigner $uriSigner): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            throw new NotFoundHttpException('This link is not one of our alerts.');
        }

        if ($request->isMethod('POST')) {
            $user->setOutingAlerts(false);
            $this->getEntityManager()->flush();

            // A mail app reads nothing back
            if ('One-Click' === $request->request->getString('List-Unsubscribe')) {
                return new Response('', Response::HTTP_OK, ['X-Robots-Tag' => 'noindex']);
            }
        }

        $response = $this->render('user/outing-alerts.html.twig', [
            'unsubscribed' => !$user->isOutingAlerts(),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
