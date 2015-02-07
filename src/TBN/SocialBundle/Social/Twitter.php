<?php


namespace TBN\SocialBundle\Social;

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

    public function constructClient() {

        $config = [
            'consumer_key' => $this->id,
            'consumer_secret' => $this->secret,
            'oauth_token' => '',
            'oauth_token_secret' => ''
        ];

        $this->client = new SingleUserAuth($config, new ArraySerializer());
    }

    public function getNumberOfCount() {
        try {
            $site = $this->siteManager->getCurrentSite();

            if($site !== null)
            {
                $page = $this->client->get('users/show', ['screen_name' => $site->getTwitterIdPage()]);
                if(isset($page['followers_count']))
                {
                    return $page['followers_count'];
                }
            }
        }catch(\Exception $e)
        {
            //TODO: logger
        }

        return 0;
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {

        $info = $user->getInfo();
        if($user->hasRole("ROLE_TWITTER") && $agenda->getTweetPostId() == null && $info !== null && $info->getTwitterAccessToken() !== null)
        {
            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token'           => $info->getTwitterAccessToken(),
                'oauth_token_secret'    => $info->getTwitterTokenSecret()
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());

            $ads = " ".$this->getLink($agenda)." #".$this->siteManager->getCurrentSite()->getNom()."ByNight";

            $status = substr($agenda->getNom(),0,140-strlen($ads)).$ads;

            $reponse = $client->post('statuses/update', [
                 'status' => $status
            ]);

            if(isset($reponse->id_str))
            {
                $agenda->setTweetPostId($reponse->id_str);
            }
        }
    }

    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {

        $info = $this->siteManager->getSiteInfo();
        if($user->hasRole("ROLE_TWITTER") && $agenda->getTweetPostSystemId() === null && $agenda->getTweetPostId() !== null && $info->getTwitterAccessToken() !== null)
        {

            $config = [
                'consumer_key' => $this->id,
                'consumer_secret' => $this->secret,
                'oauth_token' => $info->getTwitterAccessToken(),
                'oauth_token_secret' => $info->getTwitterTokenSecret()
            ];

            $client = new SingleUserAuth($config, new ArraySerializer());

            $ads = sprintf(" %s #%sByNight",$this->getLink($agenda),$this->siteManager->getCurrentSite()->getNom());
            $titre = sprintf("%s présente %s",$user->getUsername(),$agenda->getNom());
            $status = substr($titre,0,140-strlen($ads)).$ads;

            $reponse = $client->post('statuses/update', [
                 'status' => $status
            ]);

            if(isset($reponse->id_str))
            {
                $agenda->setTweetPostSystemId($reponse->id_str);
            }
            /*
            $reponse = $client->post('statuses/retweet/'.$agenda->getTweetPostId());

            if(isset($reponse->id_str))
            {
                $agenda->setTweetPostSystemId($reponse->id_str);
            }*/
        }
    }

    protected function getName() {
        return "Twitter";
    }
}
