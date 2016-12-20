<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/12/2016
 * Time: 18:55
 */

namespace TBN\MajDataBundle\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;


abstract class Updater
{
    const PAGINATION_SIZE = 200;
    const POOL_SIZE = 10;

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
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
                $responses[$index] = null;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }
}