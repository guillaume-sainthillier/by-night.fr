<?php

namespace AppBundle\Social;

use Doctrine\Common\Persistence\ObjectManager;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\FacebookResponse;
use Facebook\GraphNodes\GraphNode;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use AppBundle\Entity\Agenda;
use AppBundle\App\AppManager;
use AppBundle\Picture\EventProfilePicture;
use AppBundle\Utils\Monitor;
use AppBundle\Entity\SiteInfo;
use AppBundle\Site\SiteManager;
use AppBundle\Entity\User;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Description of Facebook.
 *
 * @author guillaume
 */
class FacebookAdmin extends FacebookEvents
{
    /**
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     * @var array
     */
    protected $cache;

    /**
     * @var ObjectManager
     */
    protected $om;

    /**
     * @var array
     */
    protected $oldIds;

    /**
     * @var bool
     */
    protected $_isInitialized;

    /**
     * @var string
     */
    protected $pageAccessToken;

    public function __construct($config, SiteManager $siteManager, TokenStorageInterface $tokenStorage, RouterInterface $router, SessionInterface $session, RequestStack $requestStack, LoggerInterface $logger, EventProfilePicture $eventProfilePicture, AppManager $appManager, ObjectManager $om)
    {
        parent::__construct($config, $siteManager, $tokenStorage, $router, $session, $requestStack, $logger, $eventProfilePicture, $appManager);

        $this->om              = $om;
        $this->cache           = [];
        $this->oldIds          = [];
        $this->_isInitialized  = false;
        $this->pageAccessToken = null;
    }

    protected function init()
    {
        parent::init();

        if (!$this->_isInitialized) {
            $this->_isInitialized = true;
            $this->siteInfo       = $this->siteManager->getSiteInfo();

            if ($this->siteInfo && $this->siteInfo->getFacebookAccessToken()) {
                $this->client->setDefaultAccessToken($this->siteInfo->getFacebookAccessToken());
            }
            $this->guessAppAccessToken();
        }
    }

    protected function guessAppAccessToken()
    {
        $this->pageAccessToken = $this->client->getApp()->getAccessToken();
    }

    protected function getAccessToken()
    {
        return $this->pageAccessToken ?: ($this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
    }

    protected function getPageAccessToken()
    {
        $this->init();

        $accessToken = $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null;
        $response    = $this->client->get('/' . $this->appManager->getFacebookIdPage() . '?fields=access_token', [], $accessToken);
        $datas       = $response->getDecodedBody();

        return $datas['access_token'];
    }

    public function postNews($title, $url, $imageUrl)
    {
        //Authentification
        $accessToken = $this->getPageAccessToken();

        $response = $this->client->post('/' . $this->appManager->getFacebookIdPage() . '/feed/', [
            'message'     => $title,
            'name'        => 'By Night Magazine',
            'link'        => $url,
            'picture'     => $imageUrl,
            'description' => $title,
        ], $accessToken);

        $post = $response->getGraphNode();

        return $post->getField('id');
    }

    protected function afterPost(User $user, Agenda $agenda)
    {
        if (null === $agenda->getFbPostSystemId()) {
            $accessToken = $this->getPageAccessToken();
            $dateDebut   = $this->getReadableDate($agenda->getDateDebut());
            $dateFin     = $this->getReadableDate($agenda->getDateFin());
            $date        = $this->getDuree($dateDebut, $dateFin);
            $place       = $agenda->getPlace();
            $message     = $user->getUsername() . ' présente : ' . $agenda->getNom() . ($place ? ' @ ' . $place->getNom() : '');

            //Authentification
            $request = $this->client->post('/' . $this->appManager->getFacebookIdPage() . '/feed', [
                'message'     => $message,
                'name'        => $agenda->getNom(),
                'link'        => $this->getLink($agenda),
                'picture'     => $this->getLinkPicture($agenda),
                'description' => $date . '. ' . strip_tags($agenda->getDescriptif()),
                'actions'     => json_encode([
                    [
                        'name' => $user->getUsername() . ' sur By Night',
                        'link' => $this->getMembreLink($user),
                    ],
                ]),
            ], $accessToken);

            $post = $request->getGraphNode();
            $agenda->setFbPostSystemId($post->getField('id'));
        }
    }

    public function getNumberOfCount()
    {
        $this->init();

        try {
            $page = $this->getPageFromId($this->appManager->getFacebookIdPage(), ['fields' => 'fan_count']);

            return $page->getField('fan_count');
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return 0;
    }

    private function getOjectsFromIds(array $ids, callable $requestFunction, callable $dataHandlerFunction, $idsPerRequest = 50, $requestPerBatch = 50)
    {
        $idsPerBatch = $requestPerBatch * $idsPerRequest;
        $nbBatchs    = ceil(count($ids) / $idsPerBatch);
        $finalDatas  = [];
        for ($i = 0; $i < $nbBatchs; ++$i) {
            $requests     = [];
            $batch_ids    = array_slice($ids, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);

            try {
                for ($j = 0; $j < $nbIterations; ++$j) {
                    $current_ids = array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[]  = $requestFunction($current_ids);
                }

                //Exécution du batch
                Monitor::bench('fb::getOjectsFromIds', function () use (&$requests, &$finalDatas, $dataHandlerFunction) {
                    $responses = $this->client->sendBatchRequest($requests, $this->getAccessToken());

                    //Traitement des réponses
                    foreach ($responses as $response) {
                        if ($response->isError()) {
                            $e = $response->getThrownException();
                            Monitor::writeln('<error>Erreur dans le batch de la récupération des objets FB : ' . ($e ? $e->getMessage() : 'Erreur Inconnue') . '</error>');
                        } else {
                            $datas = $this->findAssociativeEvents($response);
                            foreach ($datas as $data) {
                                $currentDatas = $dataHandlerFunction($data);
                                foreach ($currentDatas as $key => $currentData) {
                                    $finalDatas[$key] = $currentData;
                                }
                            }
                        }
                    }
                });
            } catch (FacebookSDKException $ex) {
                Monitor::writeln('<error>Erreur dans la récupération détaillée des objets : ' . $ex->getMessage() . '</error>');
            }
        }

        return $finalDatas;
    }

    public function getUserStatsFromIds(array $ids_users, $idsPerRequest = 50)
    {
        $this->init();
        $requestFunction = function (array $current_ids) {
            return $this->client->request('GET', '/', [
                'ids'    => implode(',', $current_ids),
                'fields' => self::USERS_FIELDS,
            ], $this->getAccessToken());
        };

        $dataHandlerFunction = function (GraphNode $data) {
            return [$data->getField('id') => [
                'url' => $this->getPagePictureURL($data, false),
            ]];
        };

        return $this->getOjectsFromIds($ids_users, $requestFunction, $dataHandlerFunction, $idsPerRequest);
    }

    public function getEventStatsFromIds(array $ids_event, $idsPerRequest = 50)
    {
        $this->init();
        $requestFunction = function (array $current_ids) {
            return $this->client->request('GET', '/', [
                'ids'    => implode(',', $current_ids),
                'fields' => self::STATS_FIELDS,
            ], $this->getAccessToken());
        };

        $dataHandlerFunction = function (GraphNode $data) {
            return [$data->getField('id') => [
                'participations' => $data->getField('attending_count'),
                'interets'       => $data->getField('maybe_count'),
                'url'            => $this->getPagePictureURL($data),
            ]];
        };

        return $this->getOjectsFromIds($ids_event, $requestFunction, $dataHandlerFunction, $idsPerRequest);
    }

    public function updateEventStatut($id_event, $userAccessToken, $isParticiper)
    {
        $this->init();

        try {
            $url = $id_event . '/' . ($isParticiper ? 'attending' : 'maybe');
            $this->client->sendRequest('POST', $url, [], $userAccessToken);

            return true;
        } catch (FacebookSDKException $ex) {
            $this->logger->critical($ex);
        }

        return false;
    }

    public function getUserEventStats($id_event, $id_user, $userAccessToken)
    {
        $this->init();
        $stats = ['participer' => false, 'interet' => false];

        try {
            $url      = '/' . $id_event . '/%s/' . $id_user;
            $requests = array(
                $this->client->request('GET', sprintf($url, 'attending'), [], $userAccessToken),
                $this->client->request('GET', sprintf($url, 'maybe'), [], $userAccessToken),
            );

            $responses = $this->client->sendBatchRequest($requests, $userAccessToken);
            foreach ($responses as $i => $response) {
                if (!$response->isError()) {
                    $isXXX                                      = $response->getGraphEdge()->count() > 0;
                    $stats[0 === $i ? 'participer' : 'interet'] = $isXXX;
                }
            }
        } catch (FacebookSDKException $ex) {
            $this->logger->critical($ex);
        }

        return $stats;
    }

    public function getEventFromId($id_event, $fields = null)
    {
        $this->init();
        $key = 'events' . $id_event;
        if (!isset($this->cache[$key])) {
            $request = $this->client->sendRequest('GET', '/' . $id_event, [
                'fields' => $fields ?: self::FIELDS,
            ], $this->getAccessToken());

            $this->cache[$key] = $request->getGraphEvent();
        }

        return $this->cache[$key];
    }

    public function getEventsFromIds(array $ids_event, $idsPerRequest = 20, $limit = 1000)
    {
        $this->init();
        $requestPerBatch = 50;
        $idsPerBatch     = $requestPerBatch * $idsPerRequest;
        $nbBatchs        = ceil(count($ids_event) / $idsPerBatch);
        $finalEvents     = [];

        for ($i = 0; $i < $nbBatchs; ++$i) {
            $requests     = [];
            $batch_ids    = array_slice($ids_event, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_ids) / $idsPerRequest);

            try {
                for ($j = 0; $j < $nbIterations; ++$j) {
                    $current_ids = array_slice($batch_ids, $j * $idsPerRequest, $idsPerRequest);
                    $requests[]  = $this->client->request('GET', '/', [
                        'ids'    => implode(',', $current_ids),
                        'fields' => self::FIELDS,
                        'limit'  => $limit,
                    ], $this->getAccessToken());
                }

                //Exécution du batch
                Monitor::bench('fb::getEventsFromIds', function () use (&$requests, &$finalEvents, $i, $nbBatchs) {
                    $responses = $this->client->sendBatchRequest($requests, $this->getAccessToken());

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
        $this->init();
        $key = 'pages.' . $id_page;
        if (!isset($this->cache[$key])) {
            $request = $this->client->sendRequest('GET',
                '/' . $id_page,
                $params,
                $this->getAccessToken()
            );

            $this->cache[$key] = $request->getGraphPage();
        }

        return $this->cache[$key];
    }

    public function getEventMembres($id_event, $offset, $limit)
    {
        $this->init();
        $participations = [];
        $interets       = [];

        try {
            $fields = str_replace(
                ['%offset%', '%limit%'],
                [$offset, $limit],
                self::MEMBERS_FIELDS
            );

            $request = $this->client->sendRequest('GET', '/' . $id_event, [
                'fields' => $fields,
            ], $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
            $graph = $request->getGraphPage();

            $participations = $this->findPaginated($graph->getField('attending'), $limit);
            $interets       = $this->findPaginated($graph->getField('maybe'), $limit);
        } catch (FacebookSDKException $ex) {
            $this->logger->error($ex);
        }

        return [
            'interets'       => $interets,
            'participations' => $participations,
        ];
    }

    public function getEventCountStats($id_event)
    {
        $this->init();
        $request = $this->client->sendRequest('GET', '/' . $id_event, [
            'fields' => 'attending_count,maybe_count',
        ], $this->getAccessToken());

        $graph = $request->getGraphPage();

        return [
            'participations' => $graph->getField('attending_count'),
            'interets'       => $graph->getField('maybe_count'),
        ];
    }

    public function getIdsToMigrate()
    {
        return $this->oldIds;
    }

    private function handleResponseException(FacebookResponseException $e)
    {
        if (preg_match("#ID (\d+) was migrated to \w+ ID (\d+)#i", $e->getMessage(), $matches)) {
            $this->oldIds[$matches[1]] = $matches[2];
        }
    }

    private function handleEdge(array $datas, $edge, callable $getParams, callable $responseToDatas, $idsPerRequest = 10, $requestsPerBatch = 50, $accessToken = null)
    {
        if (!$accessToken) {
            $accessToken = $this->getAccessToken();
        }

        $idsPerBatch = $requestsPerBatch * $idsPerRequest;
        $nbBatchs    = ceil(count($datas) / $idsPerBatch);
        $finalNodes  = [];

        //        $nbBatchs = min($nbBatchs, 5); //TODO: Supprimer ça
        for ($i = 0; $i < $nbBatchs; ++$i) {
            $requests     = [];
            $batch_datas  = array_slice($datas, $i * $idsPerBatch, $idsPerBatch);
            $nbIterations = ceil(count($batch_datas) / $idsPerRequest);

            for ($j = 0; $j < $nbIterations; ++$j) {
                $current_datas = array_slice($batch_datas, $j * $idsPerRequest, $idsPerRequest);
                $params        = call_user_func($getParams, $current_datas);
                $requests[]    = $this->client->request('GET', $edge, $params, $accessToken);
            }

            //Exécution du batch
            $currentNodes = Monitor::bench(sprintf('fb::handleEdge (%s)', $edge), function () use ($requests, $responseToDatas, $edge, $i, $nbBatchs, $accessToken) {
                $responses = $this->client->sendBatchRequest($requests, $accessToken);

                $currentNodes = [];
                //Traitement des réponses
                $fetchedNodes = 0;
                foreach ($responses as $response) {
                    /*
                     * @var FacebookResponse
                     */
                    if ($response->isError()) {
                        $e = $response->getThrownException();
                        Monitor::writeln(sprintf(
                            "<error>Erreur dans le parcours de l'edge %s : %s</error>",
                            $edge,
                            $e ? $e->getMessage() : 'Erreur Inconnue'
                        ));

                        if ($e instanceof FacebookResponseException) {
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

    public function getEventsFromUsers(array $id_users, \DateTime $since)
    {
        $this->init();

        return $this->handleEdge($id_users, '/events', function (array $current_ids) use ($since) {
            return [
                'ids'    => implode(',', $current_ids),
                'since'  => $since->format('Y-m-d'),
                'fields' => self::FIELDS,
                'limit'  => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    public function getEventsFromPlaces(array $id_places, \DateTime $since)
    {
        $this->init();

        return $this->handleEdge($id_places, '/events', function (array $current_ids) use ($since) {
            return [
                'ids'    => implode(',', $current_ids),
                'since'  => $since->format('Y-m-d'),
                'fields' => self::FIELDS,
                'limit'  => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginatedNodes($response);
        });
    }

    public function getEventsFromKeywords(array $keywords, \DateTime $since)
    {
        $this->init();

        return $this->handleEdge($keywords, '/search', function (array $keywords) use ($since) {
            $keyword = $keywords[0];

            return [
                'q'      => sprintf('"%s"', $keyword),
                'type'   => 'event',
                'fields' => self::FIELDS,
                'since'  => $since->format('Y-m-d'),
                'limit'  => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1, 50, $this->siteInfo ? $this->siteInfo->getFacebookAccessToken() : null);
    }

    public function getPlacesFromGPS(array $coordonnees)
    {
        $this->init();

        return $this->handleEdge($coordonnees, '/search', function (array $coordonnees) {
            $coordonnee = $coordonnees[0];

            return [
                'q'        => '',
                'type'     => 'place',
                'center'   => sprintf('%s,%s', $coordonnee['latitude'], $coordonnee['longitude']),
                'distance' => 4000,
                'fields'   => 'id',
                'limit'    => 1000,
            ];
        }, function (FacebookResponse $response) {
            return $this->findPaginated($response->getGraphEdge());
        }, 1);
    }

    public function setSiteInfo(SiteInfo $siteInfo)
    {
        $this->siteInfo = $siteInfo;

        return $this;
    }
}
