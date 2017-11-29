<?php

namespace AppBundle\Social;

use Google_Client;
use Symfony\Component\Routing\Generator\UrlGenerator;
use AppBundle\Entity\Agenda;
use AppBundle\Entity\User;

/**
 * Description of Twitter.
 *
 * @author guillaume
 */
class Google extends Social
{
    protected $key;

    /**
     * @var Google_Client
     */
    protected $client;

    public function constructClient()
    {
        $api_id     = $this->id;
        $api_secret = $this->secret;
        $this->key  = $this->config['key'];

        $this->client = new Google_Client();
        $this->client->setClientId($api_id);
        $this->client->setClientSecret($api_secret);
        $this->client->setDeveloperKey($this->key);
        $this->client->setScopes([
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/plus.me',
            'https://www.googleapis.com/auth/plus.login',
            'https://www.googleapis.com/auth/plus.stream.read',
            'https://www.googleapis.com/auth/plus.stream.write',
        ]);
    }

    public function getNumberOfCount()
    {
        throw new \BadMethodCallException('Not implemented');
    }

    protected function post(User $user, Agenda $agenda)
    {
        return; //Wait Google api fix
    }

    protected function getName()
    {
        return 'Google';
    }

    protected function afterPost(User $user, Agenda $agenda)
    {
    }
}
