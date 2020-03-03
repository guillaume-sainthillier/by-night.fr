<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Social;

use App\App\Location;
use Exception;
use TwitterOAuth\Auth\SingleUserAuth;
use TwitterOAuth\Serializer\ArraySerializer;

/*
 * Serializer Namespace
 */

class Twitter extends Social
{
    /**
     * @var SingleUserAuth
     */
    protected $client;

    public function constructClient()
    {
        $config = [
            'consumer_key' => $this->id,
            'consumer_secret' => $this->secret,
            'oauth_token' => '',
            'oauth_token_secret' => '',
        ];

        $this->client = new SingleUserAuth($config, new ArraySerializer());
    }

    public function getTimeline(Location $location, $max_id, $limit)
    {
        $this->init();

        $name = $location->isCountry() ? $location->getCountry()->getName() : $location->getCity()->getName();
        try {
            $params = [
                'q' => \sprintf('#%s filter:safe', $name),
                'lang' => 'fr',
                'result_type' => 'recent',
                'count' => $limit,
            ];

            if ($max_id) {
                $params['max_id'] = $max_id;
            }

            return $this->client->get('search/tweets', $params);
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        return [];
    }

    protected function getName()
    {
        return 'Twitter';
    }
}
