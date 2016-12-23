<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/12/2016
 * Time: 18:55
 */

namespace TBN\MajDataBundle\Updater;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

use Doctrine\ORM\EntityManager;
use TBN\SocialBundle\Social\FacebookAdmin;

abstract class Updater
{
    const PAGINATION_SIZE = 200;
    const POOL_SIZE = 10;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var FacebookAdmin
     */
    protected $facebookAdmin;

    public function __construct(EntityManager $entityManager, FacebookAdmin $facebookAdmin)
    {
        $this->entityManager = $entityManager;
        $this->facebookAdmin = $facebookAdmin;

        $this->client = new Client([
            'verify' => false
        ]);
    }

    protected function downloadUrls(array $urls) {
        $requests = [];
        foreach($urls as $i => $url) {
            $requests[$i] = new Request('GET', $url);
        }

        $responses = [];
        $pool = new Pool($this->client, $requests, [
            'concurrency' => self::POOL_SIZE,
            'fulfilled' => function ($response, $index) use(& $responses) {
                $responses[$index] = (string)$response->getBody();
            },
            'rejected' => function ($reason, $index) use(& $responses) {
                dump($reason);
                $responses[$index] = null;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }
}