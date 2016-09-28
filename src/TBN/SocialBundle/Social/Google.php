<?php

namespace TBN\SocialBundle\Social;

use \Google_Client;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Description of Twitter
 *
 * @author guillaume
 */
class Google extends Social
{
    protected $key;

    /**
     *
     * @var Google_Client $client
     */
    protected $client;

    public function constructClient()
    {
        $api_id = $this->id;
        $api_secret = $this->secret;
        $this->key = $this->config['key'];

        $this->client = $client = new Google_Client();
        $this->client->setClientId($api_id);
        $this->client->setClientSecret($api_secret);
        $this->client->setDeveloperKey($this->key);
        $this->client->setScopes([
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/plus.me',
            'https://www.googleapis.com/auth/plus.login',
            'https://www.googleapis.com/auth/plus.stream.read',
            'https://www.googleapis.com/auth/plus.stream.write'
        ]);
    }

    public function getNumberOfCount()
    {
        $site = $this->siteManager->getCurrentSite();
        $router = $this->router;

        if ($site !== null) {
            try {
                $url = $router->generate("tbn_main_index", [], UrlGenerator::ABSOLUTE_URL);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://clients6.google.com/rpc");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p",
                "params":{"nolog":true,"id":"' . $url . '","source":"widget","userId":"@viewer","groupId":"@self"},
                "jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));

                $result = curl_exec($ch);

                curl_close($ch);
                $json = json_decode($result, true);

                return intval($json[0]['result']['metadata']['globalCounts']['count']);
            } catch (\Exception $ex) {
            }
        }

        return 0;
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda)
    {

        return; //Wait Google api fix
    }

    protected function getName()
    {
        return "Google";
    }

    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda)
    {

    }

}
