<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use Abraham\TwitterOAuth\TwitterOAuth;
use App\App\Location;
use Exception;

class Twitter extends Social
{
    private ?TwitterOAuth $client = null;

    /**
     * {@inheritDoc}
     */
    protected function constructClient(): void
    {
        $this->client = new TwitterOAuth($this->id, $this->secret);
    }

    public function getTimeline(Location $location, ?int $max_id, int $limit): array
    {
        $this->init();

        $name = $location->isCountry() ? $location->getCountry()->getName() : $location->getCity()->getName();
        $params = [
            'q' => \sprintf('#%s filter:safe', $name),
            'lang' => 'fr',
            'result_type' => 'recent',
            'count' => $limit,
        ];

        if ($max_id) {
            $params['max_id'] = $max_id;
        }

        try {
            return json_decode(json_encode($this->client->get('search/tweets', $params), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'extra' => [
                    'params' => $params,
                ],
            ]);
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    protected function getInfoProperties(): array
    {
        return ['id', 'accessToken', 'refreshToken', 'expires', 'realname', 'nickname', 'email', 'profilePicture'];
    }

    /**
     * {@inheritDoc}
     */
    public function getInfoPropertyPrefix(): ?string
    {
        return 'twitter';
    }

    /**
     * {@inheritDoc}
     */
    protected function getRoleName(): string
    {
        return 'ROLE_TWITTER';
    }
}
