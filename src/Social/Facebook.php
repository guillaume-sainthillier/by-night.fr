<?php

namespace App\Social;

use App\Exception\SocialException;
use Facebook\Facebook as Client;

class Facebook extends Social
{
    /**
     * @var Client
     */
    protected $client;

    public function getNumberOfCount()
    {
        throw new SocialException("Les droits de l'utilisateur sont insufisants pour récupérer des infos sur une page Facebook");
    }

    public function getName()
    {
        return 'Facebook';
    }

    protected function constructClient()
    {
        $this->client = new Client([
            'app_id' => $this->id,
            'app_secret' => $this->secret,
        ]);
    }
}
