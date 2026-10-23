<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Publisher;

use App\Controller\AbstractController;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The unsubscribe link of the publishers' news (App\Newsletter\PublisherNewsSender), signed, so that it needs no
 * login. A GET only asks: the link checkers of mailboxes open the links of an e-mail. A POST unsubscribes, from the
 * page's button or from the mailbox's own (List-Unsubscribe-Post, RFC 8058), which posts to the same URL.
 */
final class PublisherNewsController extends AbstractController
{
    #[Route(path: '/nouveautes-organisateurs/desinscription/{id<\d+>}', name: 'app_publisher_news_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(Request $request, User $user, UriSigner $uriSigner): Response
    {
        if (!$uriSigner->checkRequest($request)) {
            throw $this->createNotFoundException('Invalid unsubscribe link.');
        }

        $unsubscribed = false;
        if ($request->isMethod('POST')) {
            $user->setPublisherNews(false);
            $this->getEntityManager()->flush();
            $unsubscribed = true;
        }

        return $this->render('publisher/news-unsubscribe.html.twig', [
            'subscribed' => $user->isPublisherNews(),
            'unsubscribed' => $unsubscribed,
        ]);
    }
}
