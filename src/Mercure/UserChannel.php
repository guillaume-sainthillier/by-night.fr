<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Mercure;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Update;
use Twig\Attribute\AsTwigFunction;

/**
 * A member's private Mercure channel: every update about their resources (an import, an export…) is published with
 * the resource's IRI as canonical topic and the channel as alternate topic, so a page subscribes once, to the channel,
 * and tells the resources apart by the payload's "@id".
 *
 * A page subscribes with user_channel_hub(), which gives the hub's URL matching the channel and sets the subscriber
 * cookie granting that one topic (as the Twig mercure() bridge does), once per request: the personal space's layout
 * and the page itself may both ask.
 */
final readonly class UserChannel
{
    public function __construct(
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
        private ?HubInterface $hub = null,
        private ?Authorization $authorization = null,
        private ?RequestStack $requestStack = null,
    ) {
    }

    #[AsTwigFunction(name: 'user_channel')]
    public function topic(User $user): string
    {
        return \sprintf('%s/users/%d', rtrim($this->appUrl, '/'), $user->getId());
    }

    /**
     * The hub's URL subscribing to the member's channel; sets the cookie that lets them in, once per request.
     */
    #[AsTwigFunction(name: 'user_channel_hub')]
    public function subscribeUrl(User $user): string
    {
        $topic = $this->topic($user);
        $request = $this->requestStack?->getMainRequest();
        if (null !== $request && null !== $this->authorization && !isset($request->attributes->get('_mercure_authorization_cookies', [])[''])) {
            $this->authorization->setCookie($request, [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])]);
        }

        return \sprintf('%s?match=%s', $this->hub?->getPublicUrl() ?? '', rawurlencode($topic));
    }

    /**
     * A private update about one of the member's resources: its IRI first (canonical topic), then their channel.
     *
     * @param string               $type the SSE event type the page listens to (addEventListener)
     * @param array<string, mixed> $data the resource's whole state: a page that missed an update catches up with the next one
     */
    public function update(User $user, string $iri, string $type, array $data): Update
    {
        return new Update(
            [$iri, $this->topic($user)],
            json_encode(['@id' => $iri] + $data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            private: true,
            type: $type,
        );
    }
}
