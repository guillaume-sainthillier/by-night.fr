<?php

namespace App\Command;

use App\Entity\Event;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AppEventsMigrateCommand extends AppCommand
{
    private const EVENTS_PER_TRANSACTION = 500;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, ?string $name = null)
    {
        parent::__construct($name);

        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('app:events:migrate')
            ->setDescription('Migrer les événements sur By Night');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->entityManager;

        $events = $em->createQuery('SELECT
                a.id
                FROM App:Event a
                WHERE a.url IS NOT NULL')
            ->getArrayResult();

        $events = array_map('current', $events);
        $chunks = array_chunk($events, self::EVENTS_PER_TRANSACTION);

        Monitor::createProgressBar(\count($chunks));
        foreach ($chunks as $chunk) {
            $events = $em->getRepository(Event::class)
                ->findBy(['id' => $chunk]);

            $urls = [];

            Monitor::advanceProgressBar();
            foreach ($events as $i => $event) {
                /** @var Event $event */
                $url = $event->getUrl();
                $url = str_replace('http://', 'https://', $url);
                $url = str_replace('https://images1.soonnight.com', 'https://www.soonnight.com', $url);
                $url = str_replace('https://static-site.soonnight.com', 'https://www.soonnight.com', $url);

                $urls[$i] = $url;
                $event->setUrl($url);
            }

            $responses = $this->ping($urls);

            foreach ($responses as $i => $response) {
                $event = $events[$i];
                $responseCode = $response;

                if ($responseCode < 200 || $responseCode > 302) {
                    Monitor::writeln(sprintf('<error>%s</error> Not found', $event->getUrl()));
                    $event->setUrl(null);
                }
            }

            $em->flush();
            $em->clear(Event::class);
        }
        Monitor::finishProgressBar();
    }

    protected function ping(array $urls)
    {
        $client = new Client();
        $requests = function ($urls) {
            foreach ($urls as $i => $url) {
                yield $i => new Request('HEAD', $url);
            }
        };

        $responses = [];
        $pool = new Pool($client, $requests($urls), [
            'concurrency' => 10,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$responses) {
                $responses[$index] = $response->getStatusCode();
            },
            'rejected' => function (RequestException $reason, $index) use (&$responses) {
                $responses[$index] = $reason->getResponse() ? $reason->getResponse()->getStatusCode() : 404;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }
}
