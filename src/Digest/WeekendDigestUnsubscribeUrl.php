<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Digest;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The link of a weekend selection that unsubscribes its member without a login: signed with the app secret, so that
 * no one unsubscribes another member by changing the id, and without expiry, as an old e-mail must still work.
 * Mailboxes post to it on their own "Se désabonner" button (RFC 8058).
 */
final readonly class WeekendDigestUnsubscribeUrl
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
    ) {
    }

    public function generate(User $user): string
    {
        return $this->uriSigner->sign($this->urlGenerator->generate(
            'app_weekend_digest_unsubscribe',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ));
    }

    public function isValid(Request $request): bool
    {
        return $this->uriSigner->checkRequest($request);
    }
}
