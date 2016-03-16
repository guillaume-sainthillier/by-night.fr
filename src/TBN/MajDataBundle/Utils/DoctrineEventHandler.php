<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/03/2016
 * Time: 19:16
 */

namespace TBN\MajDataBundle\Utils;

use Doctrine\Common\Cache\Cache;
use Doctrine\ORM\EntityManagerInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\SocialBundle\Social\FacebookAdmin;

class DoctrineEventHandler
{
    private $em;
    private $repoAgenda;
    private $repoSite;
    private $repoPlace;
    private $handler;
    private $firewall;
    private $api;
    private $cache;

    private $places;
    private $newPlaces;
    private $sites;
    private $newAgendas;
    private $agendas;
    private $withExplorations;

    private $stats;

    public function __construct(EntityManagerInterface $em, EventHandler $handler, Firewall $firewall, FacebookAdmin $api, Cache $cache)
    {
        $this->em = $em;
        $this->repoAgenda = $em->getRepository('TBNAgendaBundle:Agenda');
        $this->repoPlace = $em->getRepository('TBNAgendaBundle:Place');
        $this->repoSite = $em->getRepository('TBNMainBundle:Site');
        $this->handler = $handler;
        $this->firewall = $firewall;
        $this->cache = $cache;
        $this->api = $api;

        $this->withExplorations = false;
        $this->sites = [];
        $this->places = [];
        $this->newPlaces = [];
        $this->agendas = [];
        $this->newAgendas = [];
        $this->stats = [];
    }

    public function getStats()
    {
        return $this->stats;
    }

    public function init(Site $site = null, $withExplorations = false)
    {
        $this->withExplorations = $withExplorations;
        if ($site === null) {
            $sites = $this->repoSite->findAll();
        } else {
            $sites = [$site];
        }

        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0
        ];

        foreach ($sites as $currentSite) {
            $this->sites[$currentSite->getId()] = $currentSite;
            $this->places[$currentSite->getId()] = [];
            $this->newPlaces[$currentSite->getId()] = [];
            $this->agendas[$currentSite->getId()] = [];
            $this->explorations[$currentSite->getId()] = [];
            $this->newExplorations[$currentSite->getId()] = [];
            $places = $this->repoPlace->findBy(['site' => $currentSite->getId()]);
            foreach ($places as $place) {
                $this->places[$currentSite->getId()][$place->getId()] = $place;
            }

            if ($withExplorations === true) {
                $this->firewall->loadExplorations($site);
            }
        }
    }

    public function updateFBEventOfWeek($fullMode, $downloadImage = false) {
        $batchSize = 50;
        $agendas = [];
        $results = $this->repoAgenda->findAllOfWeek();
        $ids = [];
        foreach($results as $result) {
            $ids[] = $result->getFacebookEventId();
            $agendas[$result->getFacebookEventId()] = $result;
        }

        $i = 0;
        if($fullMode) {
            $fbEvents = $this->api->getEventFullStatsFromIds($ids);
        }else {
            $fbEvents = $this->api->getEventStatsFromIds($ids);
        }

        foreach($fbEvents as $id => $fbEvent) {
            $agenda = $agendas[$id];
            $oldURL = $agenda->getUrl();
            $agenda->setFbParticipations($fbEvent['participations'])
                ->setFbInterets($fbEvent['interets'])
                ->setUrl($fbEvent['url']);
            if ($downloadImage && $agenda->getUrl() !== null && $agenda->getUrl() !== $oldURL) {
                Monitor::bench('downloadImage', function() use(&$agenda) {
                    $this->handler->downloadImage($agenda);
                });
            }
            $agenda->preDateModification();
            $this->em->merge($agenda);

            if($fullMode) {
                $key = 'fb.stats.'. $id;
                $this->cache->save($key, $fbEvent["membres"]);
            }

            if($i % $batchSize === ($batchSize - 1)) {
                $this->flush();
            }
            $i++;
        }

        return $i;
    }

    protected function preHandleEvent(Agenda &$agenda)
    {
        $siteId = $agenda->getSite()->getId();
        $key = $this->getAgendaCacheKey($agenda);

        if (!isset($this->agendas[$siteId][$key])) {
            $this->newAgendas[$siteId][$key] = [];
            $this->agendas[$siteId][$key] = [];
            $agendas = Monitor::bench('findAllByDate', function() use(&$agenda) {
                return $this->repoAgenda->findAllByDate($agenda);
            });
            foreach ($agendas as $currentAgenda) {
                $this->agendas[$siteId][$key][$currentAgenda->getId()] = $currentAgenda;
            }
        }

        return array_merge($this->agendas[$siteId][$key], $this->newAgendas[$siteId][$key]);
    }

    protected function postHandleEvent(Agenda &$agenda)
    {
        $siteId = $agenda->getSite()->getId();
        $key = $this->getAgendaCacheKey($agenda);

        if ($agenda->getId()) {
            $this->agendas[$siteId][$key][$agenda->getId()] = $agenda;
        } else {
            $hashId = spl_object_hash($agenda);
            $this->newAgendas[$siteId][$key][$hashId] = $agenda;
        }
    }

    protected function postMerge(Agenda &$agenda)
    {
        $place = $agenda->getPlace();
        $siteId = $agenda->getSite()->getId();

        if ($place !== null) {
            if ($place->getId()) {
                $this->places[$siteId][$place->getId()] = $place;
            } else {
                $hashId = spl_object_hash($place);
                $this->newPlaces[$siteId][$hashId] = $place;
            }
        }
    }

    protected function postFlush()
    {
        foreach ($this->newPlaces as $siteId => $newPlaces) {
            foreach ($newPlaces as $newPlace) {
                $this->places[$siteId][$newPlace->getId()] = $newPlace;
            }
            $this->newPlaces[$siteId] = [];
        }

        foreach ($this->newAgendas as $siteId => $newAgendas) {
            foreach ($newAgendas as $key => $newDateAgendas) {
                foreach ($newDateAgendas as $newAgenda) {
                    $this->agendas[$siteId][$key][$newAgenda->getId()] = $newAgenda;
                }
                $this->newAgendas[$siteId][$key] = [];
            }
        }
    }

    public function flush()
    {
        return Monitor::bench('flush', function () {
            if ($this->withExplorations === true) {
//                Gestion des explorations + historique de la maj
                $explorations = $this->firewall->getExplorationsToSave();
                $this->stats['nbExplorations'] += count($explorations);
                foreach ($explorations as $exploration) {
                    $this->em->merge($exploration);
                }
                $this->firewall->flushNewExplorations();
            }

            $this->em->flush();
            $this->em->clear();
            $this->postFlush();
        });
    }

    private function getAgendaCacheKey(Agenda $agenda)
    {
        return $agenda->getDateDebut()->format('Y-m-d') . '.' . $agenda->getDateFin()->format('Y-m-d');
    }

    public function handleEvent(Agenda &$agenda, $downloadImage = true)
    {
        return Monitor::bench('handle', function () use (&$agenda, $downloadImage) {
            $site = $agenda->getSite();
            $siteId = $site->getId();

            $persistedPlaces = $this->places[$siteId];
            $newPlaces = $this->newPlaces[$siteId];
            $fullPlaces = array_merge($persistedPlaces, $newPlaces);

            $retour = null;
            $agenda = $this->handler->handle($fullPlaces, $site, $agenda);
            if ($agenda !== null && $agenda->getDateDebut() instanceof \DateTime && $agenda->getDateFin() instanceof \DateTime) {
                $fullEvents = $this->preHandleEvent($agenda);
                $agenda = $this->handler->handleEvent($fullEvents, $agenda);
                if ($agenda !== null) {
                    if ($downloadImage && $agenda->getPath() === null && $agenda->getUrl() !== null) {
                        Monitor::bench('downloadImage', function() use(&$agenda) {
                            $this->handler->downloadImage($agenda);
                        });
                    }

                    $agenda = Monitor::bench('merge', function() use(&$agenda) {
                        return $this->em->merge($agenda);
                    });
                    $this->postMerge($agenda);
                    $this->postHandleEvent($agenda);

                    if ($this->firewall->isPersisted($agenda)) {
                        $this->stats['nbUpdates']++;
                    } else {
                        $this->stats['nbInserts']++;
                    }
                    $retour = $agenda;
                }
            }

            if (null === $retour) {
                $this->stats['nbBlacklists']++;
            }
            return $retour;
        });
    }

    public function handle(Agenda &$agenda)
    {
        return Monitor::bench('handle', function () use (&$agenda) {
            $site = $agenda->getSite();
            $siteId = $site->getId();

            $persistedPlaces = $this->places[$siteId];
            $newPlaces = $this->newPlaces[$siteId];

            $fullPlaces = array_merge($persistedPlaces, $newPlaces);

            $agenda = $this->handler->handle($fullPlaces, $site, $agenda);
            if ($agenda !== null) {
                $agenda->preDateModification();
                $agenda = $this->em->merge($agenda);
                $this->postMerge($agenda);
                if ($this->firewall->isPersisted($agenda)) {
                    $this->stats['nbUpdates']++;
                } else {
                    $this->stats['nbInserts']++;
                }
            } else {
                $this->stats['nbBlacklists']++;
            }

            return $agenda;
        });
    }
}