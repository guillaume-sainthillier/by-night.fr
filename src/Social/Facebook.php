<?php

namespace App\Social;

use App\Exception\SocialException;
use App\Utils\Monitor;
use DateTime;
use Facebook\Exceptions\FacebookSDKException;
use Facebook\Facebook as Client;
use Facebook\FacebookResponse;
use Facebook\GraphNodes\GraphEdge;
use Facebook\GraphNodes\GraphNode;
use IntlDateFormatter;
use Locale;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
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
