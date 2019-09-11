<?php

namespace App\Social;

use App\App\Location;
use Exception;
use TwitterOAuth\Auth\SingleUserAuth;
use TwitterOAuth\Serializer\ArraySerializer;

/*
 * Serializer Namespace
 */

/**
 * Description of Twitter.
 *
 * @author guillaume
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

    public function getNumberOfCount()
    {
        $this->init();

        try {
            $page = $this->client->get('users/show', ['screen_name' => $this->socialManager->getTwitterIdPage()]);
            if (isset($page['followers_count'])) {
                return $page['followers_count'];
            }
        } catch (Exception $e) {
            \Sentry\captureException($e);
            $this->logger->error($e);
        }

        return 0;
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
            \Sentry\captureException($e);
            $this->logger->error($e);
        }

        return [];
    }

    public function postNews($title, $url)
    {
        $info = $this->socialManager->getSiteInfo();
        if (null !== $info->getTwitterAccessToken()) {
            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token' => $info->getTwitterAccessToken(),
                'oauth_token_secret' => $info->getTwitterTokenSecret(),
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());

            $reponse = $client->post('statuses/update', [
                'status' => \sprintf('%s : %s', $title, $url),
            ]);

            if (isset($reponse->id_str)) {
                return $reponse->id_str;
            }
        }

        return null;
    }

    protected function getName()
    {
        return 'Twitter';
    }
}
