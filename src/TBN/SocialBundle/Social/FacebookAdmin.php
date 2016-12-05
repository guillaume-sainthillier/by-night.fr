<?php

namespace TBN\SocialBundle\Social;

use Doctrine\Common\Persistence\ObjectManager;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\FacebookResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use JMS\Serializer\SerializerInterface;

use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Picture\EventProfilePicture;
use TBN\MajDataBundle\Utils\Monitor;
use TBN\UserBundle\Entity\SiteInfo;
use TBN\MajDataBundle\Parser\AgendaParser;
use TBN\MainBundle\Site\SiteManager;
use TBN\UserBundle\Entity\User;

use Facebook\GraphNodes\GraphNode;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Description of Facebook
 *
 * @author guillaume
 */
class FacebookAdmin extends FacebookEvents
{

    /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

    protected $cache;
    protected $om;
    protected $oldIds;

    /**
     *
     * @var SerializerInterface
     */
    protected $serializer;

    public function __construct($config, SiteManager $siteManager, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, ObjectManager $om, SerializerInterface $serializer)
    {
        parent::__construct($config, $siteManager, $tokenStorage, $router, $session, $requestStack, $logger, $eventProfilePicture);

        $this->om = $om;
        $this->siteInfo = $this->siteManager->getSiteInfo();
        $this->serializer = $serializer;
        $this->cache = [];
        $this->oldIds = [];

        if ($this->siteInfo && $this->siteInfo->getFacebookAccessToken()) {
            $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        }
    }

    public function init()
    {
        $this->siteInfo = $this->siteManager->getSiteInfo();

        //CLI
        if ($this->isCLI()) {
            $this->siteInfo = $this->om->getRepository('TBNUserBundle:SiteInfo')->findOneBy([]);
        }

        if ($this->siteInfo && $this->siteInfo->getFacebookAccessToken()) {
            $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        }
    }

    protected function isCLI()
    {
        return php_sapi_name() === 'cli';
    }

    protected function afterPost(User $user, Agenda $agenda)
    {
        if ($agenda->getFbPostSystemId() === null && $this->siteInfo !== null && $this->siteInfo->getFacebookAccessToken() !== null) {
            $site = $this->siteManager->getCurrentSite();
            $dateDebut = $this->getReadableDate($agenda->getDateDebut());
            $dateFin = $this->getReadableDate($agenda->getDateFin());
            $date = $this->getDuree($dateDebut, $dateFin);
            $place = $agenda->getPlace();
            $message = $user->getUsername() . ' présente : ' . $agenda->getNom() . ($place ? " @ " . $place->getNom() : '');


            //Authentification
            $request = $this->client->post('/' . $site->getFacebookIdPage() . '/feed', [
                'message' => $message,
                'name' => $agenda->getNom(),
                'link' => $this->getLink($agenda),
                'picture' => $this->getLinkPicture($agenda),
                'description' => $date . '. ' . strip_tags($agenda->getDescriptif()),
                'actions' => json_encode([
                    [
                        'name' => $user->getUsername() . ' sur ' . $user->getSite()->getNom() . ' By Night',
                        'link' => $this->getMembreLink($user)
                    ]
                ])
            ], $this->siteInfo->getFacebookAccessToken());

            $post = $request->getGraphNode();
            $agenda->setFbPostSystemId($post->getField('id'));
        }
    }

    public function getNumberOfCount()
    {
        $site = $this->siteManager->getCurrentSite();

        if ($site !== null && $this->siteInfo !== null) {
            try {
                $page = $this->getPageFromId($site->getFacebookIdPage(), ['fields' => 'fan_count']);
                return $page->getField('fan_count');
            } catch (FacebookSDKException $ex) {
                $this->logger->error($ex);
            }
        }

        return 0;
    }

    public function getEventFullStatsFromIds(&$ids_event)
    {
        $idsPerRequest = 50;
        $nbBatchs = ceil(count($ids_event) / $idsPerRequest);
        $finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

        for ($i = 0; $i < $nbBatchs; $i++) {
            $requests = [];
            $batch_ids = array_slice($ids_event, $i * $idsPerRequest, $idsPerRequest);
            try {
                foreach ($batch_ids as $batch_id) {
                    $requests[] = $this->client->request('GET', '/' . $batch_id, [
                        'fields' => self::$FULL_STATS_FIELDS
                    ]);
                }

                //Exécution du batch
                Monitor::bench('fb::getEventStatsFromIds', function () use (&$requests, &$finalEvents, $i) {
                    $responses = $this->client->sendBatchRequest($requests);

                    //Traitement des réponses
                    foreach ($responses as $response) {
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la récupération des stats FB : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $data = $response->getGraphPage();
                            $finalEvents[$data->getField('id')] = [
                                'participations' => $data->getField('attending_count'),
                                'interets' => $data->getField('maybe_count'),
                                'membres' => array_merge($this->findPaginated($data->getField('attending')), $this->findPaginated($data->getField('maybe'))),
                                'url' => $this->getPagePictureURL($data)
                            ];
                        }
                    }
                });
            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération détaillée des événements : ' . $ex->getMessage() . '</error>');
            }
        }

        return $finalEvents;
    }

    public function getEventStatsFromIds(&$ids_event, $idsPerRequest = 1)
    {
        $requestPerBatch = 50;
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs = ceil(count($ids_event) / $idsPerBatch);
        $finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
        for ($i = 0; $i < $nbBatchs; $i++) {
            $requests = [];
            $batch_ids = array_slice($ids_event, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);
            try {
                for ($j = 0; $j < $nbIterations; $j++) {
                    $current_ids = array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[] = $this->client->request('GET', '/', [
                        'ids' => implode(',', $current_ids),
                        'fields' => self::$STATS_FIELDS
                    ]);
                }

                //Exécution du batch
                Monitor::bench('fb::getEventStatsFromIds', function () use (&$requests, &$finalEvents, $i, $nbBatchs) {
                    $responses = $this->client->sendBatchRequest($requests);

                    //Traitement des réponses
                    foreach ($responses as $response) {
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la récupération des stats FB : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $datas = $this->findAssociativeEvents($response);
                            foreach ($datas as $data) {
                                $finalEvents[$data->getField('id')] = [
                                    'participations' => $data->getField('attending_count'),
                                    'interets' => $data->getField('maybe_count'),
                                    'url' => $this->getPagePictureURL($data)
                                ];
                            }
                        }
                    }
                });
            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération détaillée des événements : ' . $ex->getMessage() . '</error>');
            }
        }

        return $finalEvents;
    }

    public function updateEventStatut($id_event, $userAccessToken, $isParticiper)
    {
        try {
            $url = $id_event . '/' . ($isParticiper ? 'attending' : 'maybe');
            $this->client->sendRequest('POST', $url, [], $userAccessToken);
            return true;
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return false;
    }

    public function getUserEventStats($id_event, $id_user)
    {
        $stats = ['participer' => false, 'interet' => false];

        try {
            $url = '/' . $id_event . '/%s/' . $id_user;
            $requests = array(
                $this->client->request('GET', sprintf($url, 'attending')),
                $this->client->request('GET', sprintf($url, 'maybe'))
            );

            $responses = $this->client->sendBatchRequest($requests);
            foreach ($responses as $i => $response) {
                if (!$response->isError()) {
                    $isXXX = $response->getGraphEdge()->count() > 0;
                    $stats[$i === 0 ? 'participer' : 'interet'] = $isXXX;
                }
            }
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return $stats;
    }

    public function getEventFromId($id_event, $fields = null)
    {
        $key = 'events' . $id_event;
        if (!isset($this->cache[$key])) {
            $request = $this->client->sendRequest('GET', '/' . $id_event, [
                'fields' => $fields ?: self::$FIELDS
            ], $this->siteInfo->getFacebookAccessToken());

            $this->cache[$key] = $request->getGraphEvent();
        }

        return $this->cache[$key];
    }

    public function getEventsFromIds(& $ids_event, $idsPerRequest = 20, $limit = 1000)
    {
        $requestPerBatch = 50;
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs = ceil(count($ids_event) / $idsPerBatch);
        $finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

        for ($i = 0; $i < $nbBatchs; $i++) {
            $requests = [];
            $batch_ids = array_slice($ids_event, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);
            try {
                for ($j = 0; $j < $nbIterations; $j++) {
                    $current_ids = array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[] = $this->client->request('GET', '/', [
                        'ids' => implode(',', $current_ids),
                        'fields' => self::$FIELDS,
                        'limit' => $limit
                    ]);
                }

                //Exécution du batch
                Monitor::bench('fb::getEventsFromIds', function () use (&$requests, &$finalEvents, $i, $nbBatchs) {
                    $responses = $this->client->sendBatchRequest($requests);

                    //Traitement des réponses
                    $fetchedEvents = 0;
                    foreach ($responses as $response) {
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la recherche par IDS événements : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $datas = $this->findAssociativeEvents($response);
                            $fetchedEvents += count($datas);
                            $finalEvents = array_merge($finalEvents, $datas);
                        }
                    }
                    Monitor::writeln(sprintf('%d / %d : Récupération détaillée de <info>%d</info> événement(s)', $i + 1, $nbBatchs, $fetchedEvents));
                });
            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération détaillée des événements : ' . $ex->getMessage() . '</error>');

                foreach ($batch_ids as $current_id) {
                    try {
                        $finalEvents = array_merge($finalEvents, [$this->getEventFromId($current_id)]);
                    } catch (FacebookSDKException $ex) {
                        Monitor::writeln(sprintf(
                            '<error>Erreur dans la récupération l\'événement #%s : %s</error>', $current_id, $ex->getMessage()
                        ));
                    }
                }
            }
        }

        return $finalEvents;
    }

    public function getPageFromId($id_page, $params = [])
    {
        $key = 'pages.' . $id_page;
        if (!isset($this->cache[$key])) {
            $request = $this->client->sendRequest('GET',
                '/' . $id_page,
                $params,
                $this->siteInfo->getFacebookAccessToken()
            );

            $this->cache[$key] = $request->getGraphPage();
        }

        return $this->cache[$key];
    }




    public function getEventStats($id_event)
    {
        $nbParticipations = 0;
        $nbInterets = 0;
        $participations = [];
        $interets = [];
        $image = null;

        try {
            $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

            $request = $this->client->sendRequest('GET', '/' . $id_event . '', [
                'fields' => self::$FULL_STATS_FIELDS,
                'limit' => 1000
            ]);
            $graph = $request->getGraphPage();

            $image = $this->getPagePictureURL($graph);
            $nbParticipations = $graph->getField('attending_count');
            $nbInterets = $graph->getField('maybe_count');
            $participations = $this->findPaginated($graph->getField('attending'));
            $interets = $this->findPaginated($graph->getField('maybe'));

        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return [
            'image' => $image,
            'nbParticipations' => $nbParticipations,
            'nbInterets' => $nbInterets,
            'membres' => array_merge($participations, $interets)
        ];
    }

    public function getEventCountStats($id_event)
    {
        $request = $this->client->sendRequest('GET', '/' . $id_event, [
            'fields' => 'attending_count,maybe_count'
        ], $this->siteInfo->getFacebookAccessToken());

        $graph = $request->getGraphPage();

        return [
            'participations' => $graph->getField('attending_count'),
            'interets' => $graph->getField('maybe_count')
        ];
    }

    public function getIdsToMigrate() {
        return $this->oldIds;
    }

    private function handleResponseException(FacebookResponseException $e) {
        if(preg_match("#ID (\d+) was migrated to \w+ ID (\d+)#i", $e->getMessage(), $matches)) {
            $this->oldIds[$matches[1]] = $matches[2];
        }
    }


    private function handleEdge(array $datas, $edge, callable $getParams, callable $responseToDatas, $idsPerRequest = 10, $requestsPerBatch = 50) {
        $idsPerBatch = $requestsPerBatch * $idsPerRequest;
        $nbBatchs = ceil(count($datas) / $idsPerBatch);
        $finalNodes = [];


        for ($i = 0; $i < $nbBatchs; $i++) {
            $requests = [];
            $batch_datas = array_slice($datas, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_datas) / $idsPerRequest);

            for ($j = 0; $j < $nbIterations; $j++) {
                $current_datas = array_slice($batch_datas, $j * $idsPerRequest, $idsPerRequest);
                $params = call_user_func($getParams, $current_datas);
                $requests[] = $this->client->request('GET', $edge, $params);
            }

            //Exécution du batch
            $currentNodes = Monitor::bench(sprintf('fb::handleEdge (%s)', $edge), function () use ($requests, $responseToDatas, $edge, $i, $nbBatchs) {
                $responses = $this->client->sendBatchRequest($requests, $this->siteInfo->getFacebookAccessToken());

                $currentNodes = [];
                //Traitement des réponses
                $fetchedNodes = 0;
                foreach ($responses as $response) {
                    /**
                     * @var FacebookResponse $response
                     */
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        Monitor::writeln(sprintf(
                            "<error>Erreur dans le parcours de l'edge %s : %s</error>",
                            $edge,
                            $e ? $e->getMessage() : 'Erreur Inconnue'
                        ));

                        if($e instanceof FacebookResponseException) {
                            $this->handleResponseException($e);
                        }
                    } else {
                        $datas = call_user_func($responseToDatas, $response);
                        $fetchedNodes += count($datas);
                        $currentNodes = array_merge($currentNodes, $datas);
                    }
                }
                Monitor::writeln(sprintf(
                    '%d / %d : Récupération de <info>%d</info> node(s)',
                    $i + 1,
                    $nbBatchs,
                    $fetchedNodes
                ));

                return $currentNodes;
            });

            $finalNodes = array_merge($finalNodes, $currentNodes);
        }

        return $finalNodes;
    }


    private function handleEventsEdge(& $ids, \DateTime $since, $idsPerRequest = 49, $limit = 1000)
    {
        $requestPerBatch = 50;
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs = ceil(count($ids) / $idsPerBatch);
        $finalEvents = [];
        $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());

        for ($i = 0; $i < $nbBatchs; $i++) {
            $requests = [];
            $batch_ids = array_slice($ids, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);
            try {
                for ($j = 0; $j < $nbIterations; $j++) {
                    $current_ids = array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[] = $this->client->request('GET', '/events', [
                        'ids' => implode(',', $current_ids),
                        'fields' => self::$MIN_EVENT_FIELDS,
                        'since' => $since->format('Y-m-d'),
                        'limit' => $limit
                    ]);
                }

                //Exécution du batch
                Monitor::bench('fb::handleEventsEdge', function () use (&$requests, &$finalEvents, $i, $nbBatchs) {
                    $responses = $this->client->sendBatchRequest($requests);

                    //Traitement des réponses
                    $fetchedEvents = 0;
                    foreach ($responses as $response) {
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la recherche par événements : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $datas = $this->findAssociativePaginated($response);
                            $fetchedEvents += count($datas);
                            $finalEvents = array_merge($finalEvents, $datas);
                        }
                    }
                    Monitor::writeln(sprintf('%d / %d : Récupération de <info>%d</info> événement(s)',
                        $i + 1, $nbBatchs, $fetchedEvents));
                });

            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération associatives des pages : ' . $ex->getMessage() . '</error>');

                foreach ($batch_ids as $current_id) {
                    try {
                        $request = $this->client->sendRequest('GET', '/' . $current_id . '/events', [
                            'fields' => self::$MIN_EVENT_FIELDS,
                            'limit' => $limit
                        ]);
                        $finalEvents = array_merge($finalEvents, $this->findPaginated($request->getGraphEdge()));
                    } catch (FacebookSDKException $ex) {
                        Monitor::writeln(sprintf(
                            '<error>Erreur dans la récupération des événéments de l\'objet #%s : %s</error>', $current_id, $ex->getMessage()
                        ));
                    }
                }
            }
        }

        return array_unique($finalEvents);
    }

    public function getEventsFromUsers(array $id_users, \DateTime $since)
    {
        return $this->handleEdge($id_users, '/events', function(array $current_ids) use($since) {
            return [
                'ids' => implode(',', $current_ids),
                'since' => $since->format('Y-m-d'),
                'fields' => self::$FIELDS,
                'limit' => 1000
            ];
        }, function(FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    public function getEventsFromPlaces(array $id_places, \DateTime $since)
    {
        return $this->handleEdge($id_places, '/events', function(array $current_ids) use($since) {
            return [
                'ids' => implode(',', $current_ids),
                'since' => $since->format('Y-m-d'),
                'fields' => self::$FIELDS,
                'limit' => 1000
            ];
        }, function(FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    public function getEventsFromKeywords(array $keywords, \DateTime $since) {
        return $this->handleEdge($keywords, '/search', function(array $keywords) {
            $keyword = $keywords[0];
            return [
                'q' => sprintf('"%s"', $keyword),
                'type' => 'event',
                'fields' => self::$FIELDS,
                'limit' => 1000
            ];
        }, function(FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1);
    }

    public function getPlacesFromGPS(array $coordonnees)
    {
        return $this->handleEdge($coordonnees, '/search', function(array $coordonnees) {
            $coordonnee = $coordonnees[0];
            return [
                'q' => '',
                'type' => 'place',
                'center' => sprintf("%s,%s", $coordonnee["latitude"], $coordonnee["longitude"]),
                'distance' => $coordonnee['distanceMax'] * 1000,
                'fields' => 'id',
                'limit' => 1000
            ];
        }, function(FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1);
    }

    /**
     * @param string $accessToken
     * @param string $endPoint
     * @param array $params
     * @param int $limit
     * @return array
     */
    protected function handlePaginate($accessToken, $endPoint, $params, $limit = 1000)
    {
        try {
            //Construction de la requête
            $params['limit'] = $limit;
            $response = $this->client->sendRequest('GET', $endPoint, $params, $accessToken);

            //Récupération des données
            return $this->findPaginated($response->getGraphEdge());
        } catch (FacebookSDKException $ex) {
            Monitor::writeln(sprintf('<error>Erreur dans handlePaginate : %s</error>', $ex->getMessage()));
        }

        return [];
    }

    public function setSiteInfo(SiteInfo $siteInfo)
    {
        $this->siteInfo = $siteInfo;

        return $this;
    }
}
