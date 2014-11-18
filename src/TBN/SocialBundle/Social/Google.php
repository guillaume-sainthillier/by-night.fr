<?php

namespace TBN\SocialBundle\Social;

use \Google_Client;
use \Google_Service_Plus;
use \Google_Moment;
use \Google_ItemScope;

/**
 * Description of Twitter
 *
 * @author guillaume
 */
class Google extends Social {

    /**
     *
     * @var Google_Client $client
     */
    protected $client;

    public function constructClient() {

        $api_id = $this->id;
        $api_secret = $this->secret;

        /*
          $client -> setApplicationName($this->container->getParameter('app_name'));
          $client -> setClientId($this->container->getParameter('google_app_id'));
          $client -> setClientSecret($this->container->getParameter('google_app_secret'));
          $client -> setDeveloperKey("AIzaSyAzU6G-etnZzjzxGLPVb0UrfFQeI0dZi78"); */

        $this->client = $client = new Google_Client();
        $this->client->setClientId($api_id);
        $this->client->setClientSecret($api_secret);
        $this->client->setDeveloperKey("AIzaSyBETAmun16QLnNnOtEPL4-_n-O3ApO9BEI");
        $this->client->setScopes([
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/plus.me',
            'https://www.googleapis.com/auth/plus.login',
            'https://www.googleapis.com/auth/plus.stream.read',
            'https://www.googleapis.com/auth/plus.stream.write'
        ]);
        //$this->client->authenticate("AIzaSyBETAmun16QLnNnOtEPL4-_n-O3ApO9BEI");
    }

    public function getNumberOfCount() {

        $site = $this->siteManager->getCurrentSite();
        $router = $this->router;

        if ($site !== null) {
            try
            {
                $url = $router->generate("tbn_main_index", true);
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $url . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
                $curl_results = curl_exec ($curl);
                curl_close ($curl);
                $json = json_decode($curl_results, true);

                return intval( $json[0]['result']['metadata']['globalCounts']['count'] );
            } catch (\Exception $ex) {
            }
        }

        return 0;
    }

    protected function post(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {

        return; //TODO: Wait Google api fix
        if($user->hasRole("ROLE_GOOGLE")  and $info !== null and $info->getGoogleAccessToken() !== null)
        {

            $client = new Google_Client();
            $client -> setApplicationName($this->container->getParameter('app_name'));
            $client -> setClientId($this->container->getParameter('google_app_id'));
            $client -> setClientSecret($this->container->getParameter('google_app_secret'));
            $client -> setDeveloperKey("AIzaSyAzU6G-etnZzjzxGLPVb0UrfFQeI0dZi78");


            $token = [
                "access_token" => $user->getGoogleAccessToken(),
                "refresh_token" => $user->getGoogleAccessToken(),
                "token_type" => "Bearer",
                "expires_in" => 3600,
                "id_token" => $user->getGoogleAccessToken(),
                "created" => time()
            ];

            $client->setAccessToken(json_encode($token));
            $client -> setScopes([
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'https://www.googleapis.com/auth/plus.me',
                'https://www.googleapis.com/auth/plus.login',
                'https://www.googleapis.com/auth/plus.stream.read',
                'https://www.googleapis.com/auth/plus.stream.write'
            ]);

            $requestVisibleActions = [
                'http://schemas.google.com/AddActivity',
                'http://schemas.google.com/ReviewActivity'];

            $client->setRequestVisibleActions($requestVisibleActions);
            //$client->authenticate();

            $gplus = new Google_Service_Plus($client);

            //var_dump($client->getAccessToken());
            //$me = $gplus->people->get('me');

            $moments = $gplus->moments->listMoments('me', 'vault');

            print_r($moments);


            $moment_body = new \Google_Service_Plus_Moment();
            $moment_body->setType("http://schemas.google.com/AddActivity");
            $item_scope = new \Google_Service_Plus_ItemScope();
            $item_scope->setId("target-id-1");
            $item_scope->setType("http://schemas.google.com/AddActivity");
            $item_scope->setName("The Google+ Platform");
            $item_scope->setDescription("A page that describes just how awesome Google+ is!");
            $item_scope->setImage("https://developers.google.com/+/plugins/snippet/examples/thing.png");
            $moment_body->setTarget($item_scope);
            $momentResult = $gplus->moments->insert("me", 'vault', $moment_body);

            var_dump($momentResult);
        }
    }

    protected function getName() {
        return "Google";
    }

    protected function afterPost(\TBN\UserBundle\Entity\User $user, \TBN\AgendaBundle\Entity\Agenda $agenda) {

    }
}
