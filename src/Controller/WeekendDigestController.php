<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Digest\WeekendDigestUnsubscribeUrl;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WeekendDigestController extends AbstractController
{
    /**
     * The link of a weekend selection: a page that asks for a confirmation, as the mail scanners open every link, and
     * the posts of that page and of the mailboxes' own button (RFC 8058), which unsubscribe at once. No login: the
     * signature of the link proves who it is for.
     */
    #[Route(path: '/newsletter/desinscription/{id<%patterns.id%>}', name: 'app_weekend_digest_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(Request $request, WeekendDigestUnsubscribeUrl $unsubscribeUrl, UserRepository $userRepository, int $id): Response
    {
        $user = $userRepository->find($id);
        if (null === $user || !$unsubscribeUrl->isValid($request)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST') && $user->isWeekendDigest()) {
            $user->setWeekendDigest(false);
            $this->getEntityManager()->flush();
        }

        $response = $this->render('weekend-digest/unsubscribe.html.twig', [
            'user' => $user,
            'action' => $request->getRequestUri(),
        ]);
        // Only ever linked from an e-mail
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
