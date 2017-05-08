<?php


namespace AppBundle\Social;

use AppBundle\Entity\Agenda;
use AppBundle\Entity\Site;
use AppBundle\Entity\User;
use TwitterOAuth\Auth\SingleUserAuth;
/**
 * Serializer Namespace
 */
use TwitterOAuth\Serializer\ArraySerializer;


/**
 * Description of Twitter
 *
 * @author guillaume
 */
class Twitter extends Social
{
    /**
     *
     * @var SingleUserAuth $client
     */
    protected $client;

    public function constructClient()
    {
        $config = [
            'consumer_key' => $this->id,
            'consumer_secret' => $this->secret,
            'oauth_token' => '',
            'oauth_token_secret' => ''
        ];

        $this->client = new SingleUserAuth($config, new ArraySerializer());
    }

    public function getNumberOfCount()
    {
        $this->init();
        try {
            $page = $this->client->get('users/show', ['screen_name' => $this->appManager->getTwitterIdPage()]);
            if (isset($page['followers_count'])) {
                return $page['followers_count'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return 0;
    }

    public function getTimeline(Site $site, $max_id, $limit)
    {
        $this->init();
        try {
            $params = [
                'q' => sprintf('#%s filter:safe', $site->getNom()),
                'lang' => 'fr',
                'result_type' => 'recent',
                'count' => $limit
            ];

            if ($max_id) {
                $params['max_id'] = $max_id;
            }

            return $this->client->get('search/tweets', $params);
        } catch (\Exception $e) {
            $this->logger->error($e);
        }

        return [];
    }

    public function postNews($title, $url)
    {
        $info = $this->siteManager->getSiteInfo();
        if ($info->getTwitterAccessToken() !== null) {
            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token' => $info->getTwitterAccessToken(),
                'oauth_token_secret' => $info->getTwitterTokenSecret()
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());

            $reponse = $client->post('statuses/update', [
                'status' => sprintf("%s : %s", $title, $url)
            ]);

            if (isset($reponse->id_str)) {
                return $reponse->id_str;
            }
        }
    }

    protected function post(User $user, Agenda $agenda)
    {
        $info = $user->getInfo();
        if ($user->hasRole("ROLE_TWITTER") && $agenda->getTweetPostId() === null && $info !== null && $info->getTwitterAccessToken() !== null) {
            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token' => $info->getTwitterAccessToken(),
                'oauth_token_secret' => $info->getTwitterTokenSecret()
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());

            $ads = " " . $this->getLink($agenda) . " #" . $this->siteManager->getCurrentSite()->getNom() . "ByNight";

            $status = substr($agenda->getNom(), 0, 140 - strlen($ads)) . $ads;

            $reponse = $client->post('statuses/update', [
                'status' => $status
            ]);

            if (isset($reponse->id_str)) {
                $agenda->setTweetPostId($reponse->id_str);
            }
        }
    }

    protected function afterPost(User $user, Agenda $agenda)
    {
        $info = $this->siteManager->getSiteInfo();
        if ($user->hasRole("ROLE_TWITTER") && $agenda->getTweetPostSystemId() === null && $agenda->getTweetPostId() !== null && $info->getTwitterAccessToken() !== null) {
            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token' => $info->getTwitterAccessToken(),
                'oauth_token_secret' => $info->getTwitterTokenSecret()
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());
            $ads = sprintf(" %s #%sByNight", $this->getLink($agenda), $this->siteManager->getCurrentSite()->getNom());
            $titre = sprintf("%s présente %s", $user->getUsername(), $agenda->getNom());
            $status = substr($titre, 0, 140 - strlen($ads)) . $ads;

            $reponse = $client->post('statuses/update', [
                'status' => $status
            ]);

            if (isset($reponse->id_str)) {
                $agenda->setTweetPostSystemId($reponse->id_str);
            }
        }
    }

    protected function getName()
    {
        return "Twitter";
    }
}
