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
use Symfony\Component\Mercure\Update;
use Twig\Attribute\AsTwigFunction;

/**
 * A member's private Mercure channel: every update about their resources (an import, an export…) is published with
 * the resource's IRI as canonical topic and the channel as alternate topic, so a page subscribes once, to the channel,
 * and tells the resources apart by the payload's "@id".
 *
 * A page subscribes with the Twig bridge, which also sets the subscriber cookie granting that one topic:
 *
 *     {{ mercure(user_channel(app.user), {subscribe: user_channel(app.user)}) }}
 */
final readonly class UserChannel
{
    public function __construct(
        #[Autowire('%env(APP_URL)%')]
        private string $appUrl,
    ) {
    }

    #[AsTwigFunction(name: 'user_channel')]
    public function topic(User $user): string
    {
        return \sprintf('%s/users/%d', rtrim($this->appUrl, '/'), $user->getId());
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
