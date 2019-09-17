<?php

namespace App\Social;

use App\App\SocialManager;
use App\Picture\EventProfilePicture;
use App\Utils\Monitor;
use BadMethodCallException;
use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use function GuzzleHttp\Psr7\copy_to_string;

class EventBrite extends Social
{
    /** @var Client */
    private $client;

    /** @var DoctrineCache */
    private $cache;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(array $config, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, SocialManager $socialManager, DoctrineCache $memoryCache, EntityManagerInterface $entityManager)
    {
        parent::__construct($config, $tokenStorage, $router, $session, $requestStack, $logger, $eventProfilePicture, $socialManager);
        $this->cache = $memoryCache;
        $this->entityManager = $entityManager;
    }

    public function constructClient()
    {
        $this->client = new Client([
            'base_uri' => 'https://www.eventbriteapi.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->socialManager->getSiteInfo()->getEventbriteAccessToken(),
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getEventVenues(array $ids): array
    {
        $requests = function ($ids) {
            foreach ($ids as $id) {
                yield function () use ($id) {
                    return $this->jsonRequest('/v3/venues/' . $id);
                };
            }
        };

        $results = Pool::batch($this->client, $requests($ids), [
            'concurrency' => 5,
        ]);
        $results = array_filter($results, 'is_array');

        $venues = [];
        foreach ($results as $result) {
            $venues[$result['id']] = $result;
        }

        return $venues;
    }

    public function getEventCategory(string $categoryId, string $locale)
    {
        $key = sprintf('eb.category.%s.%s', $categoryId, $locale);
        if (!$this->cache->contains($key)) {
            $result = $this
                ->jsonRequest(sprintf('/v3/categories/%s/?locale=%s', $categoryId, $locale))
                ->wait();
            $this->cache->save($key, $result);
        } else {
            $result = $this->cache->fetch($key);
        }

        return $result;
    }

    public function getEventResults(array $searchParams): PromiseInterface
    {
        $this->init();

        return $this->jsonRequest('/v3/events/search/?' . http_build_query($searchParams));
    }

    private function jsonRequest(string $uri):? PromiseInterface
    {
        return $this
            ->client
            ->getAsync($uri)
            ->then(function (ResponseInterface $response) {
                return json_decode(copy_to_string($response->getBody()), true);
            }, function (RequestException $exception) use ($uri) {
                if ($exception->getResponse() && $exception->getResponse()->getStatusCode() === 429) {
                    Monitor::writeln("<error>EVENTBRITE API LIMIT</error>");
                    sleep(3660);
                    $this->ping($this->entityManager->getConnection());
                    return $this->jsonRequest($uri);
                }

                throw $exception;
            });
    }

    private function ping(Connection $connection)
    {
        if (false === $connection->ping()) {
            $connection->close();
            $connection->connect();
        }
    }

    public function getNumberOfCount()
    {
        throw new BadMethodCallException('Not implemented');
    }

    protected function getName()
    {
        return 'EventBrite';
    }
}
