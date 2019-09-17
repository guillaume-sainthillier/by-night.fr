<?php

namespace App\Consumer;

use GuzzleHttp\Client;
use OldSound\RabbitMqBundle\RabbitMq\BatchConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;

class PurgeCdnCacheUrlConsumer extends AbstractConsumer implements BatchConsumerInterface
{
    /** @var Packages */
    private $packages;

    /** @var Client */
    private $client;

    /** @var string */
    private $cfZone;

    public function __construct(LoggerInterface $logger, Packages $packages, string $cfUserEmail, string $cfUserKey, string $cfZone)
    {
        parent::__construct($logger);

        $this->packages = $packages;
        $this->cfZone = $cfZone;

        $this->client = new Client([
            'base_uri' => 'https://api.cloudflare.com',
            'headers' => [
                'X-Auth-Email' => $cfUserEmail,
                'X-Auth-Key' => $cfUserKey,
            ]
        ]);
    }

    public function batchExecute(array $messages)
    {
        $urls = [];

        /** @var AMQPMessage $message */
        foreach ($messages as $i => $message) {
            $path = $message->getBody();
            $urls[] = $this->packages->getUrl($path, 'file');
        }

        try {
            $this->client->post(
                sprintf('/client/v4/zones/%s/purge_cache', $this->cfZone),
                ['json' => ["files" => $urls]]
            );
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return ConsumerInterface::MSG_REJECT;
        }

        return ConsumerInterface::MSG_ACK;
    }
}
