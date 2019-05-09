<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/12/2016
 * Time: 18:55.
 */

namespace App\Updater;

use App\Social\FacebookAdmin;
use Doctrine\Common\Persistence\ObjectManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\copy_to_string;

abstract class Updater
{
    const POOL_SIZE = 5;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @var FacebookAdmin
     */
    protected $facebookAdmin;

    public function __construct(ObjectManager $entityManager, FacebookAdmin $facebookAdmin)
    {
        $this->entityManager = $entityManager;
        $this->facebookAdmin = $facebookAdmin;

        $this->client = new Client();
    }

    public abstract function update(\DateTime $from);

    protected function downloadUrls(array $urls)
    {
        $requests = function ($urls) {
            foreach ($urls as $i => $url) {
                yield $i => new Request('GET', $url);
            }
        };

        $responses = [];
        $pool = new Pool($this->client, $requests($urls), [
            'concurrency' => self::POOL_SIZE,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$responses) {
                $responses[$index] = [
                    'contentType' => current($response->getHeader('Content-Type')),
                    'content' => copy_to_string($response->getBody())
                ];
            },
            'rejected' => function (RequestException $reason, $index) use (&$responses) {
                $responses[$index] = null;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }
}
