<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/03/2016
 * Time: 19:16.
 */

namespace TBN\MajDataBundle\Handler;

use Doctrine\ORM\EntityManagerInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Entity\Exploration;
use TBN\MajDataBundle\Parser\Common\FaceBookParser;
use TBN\MajDataBundle\Parser\ParserInterface;
use TBN\MajDataBundle\Reject\Reject;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\MajDataBundle\Utils\Monitor;

class DoctrineEventHandler
{
    const BATCH_SIZE = 50;

    private $em;
    private $repoAgenda;
    private $repoPlace;
    private $handler;
    private $firewall;

    private $sites;
    private $villes;

    /**
     * @var EchantillonHandler
     */
    private $echantillonHandler;

    /**
     * @var ExplorationHandler
     */
    private $explorationHandler;

    public function __construct(EntityManagerInterface $em, EventHandler $handler, Firewall $firewall, EchantillonHandler $echantillonHandler)
    {
        $this->em                 = $em;
        $this->repoAgenda         = $em->getRepository('TBNAgendaBundle:Agenda');
        $this->repoPlace          = $em->getRepository('TBNAgendaBundle:Place');
        $this->repoSite           = $em->getRepository('TBNMainBundle:Site');
        $this->handler            = $handler;
        $this->firewall           = $firewall;
        $this->echantillonHandler = $echantillonHandler;
        $this->explorationHandler = new ExplorationHandler();

        $this->output = null;

        $this->villes = [];
        $this->sites  = [];
        $this->stats  = [];
    }

    /**
     * @return ExplorationHandler
     */
    public function getExplorationHandler()
    {
        return $this->explorationHandler;
    }

    /**
     * @param Agenda $event
     *
     * @return Agenda
     */
    public function handleOne(Agenda $event)
    {
        return $this->handleMany([$event])[0];
    }

    /**
     * @param array           $events
     * @param ParserInterface $parser
     *
     * @return array
     */
    public function handleManyCLI(array $events, ParserInterface $parser)
    {
        $events = $this->handleMany($events);
        if ($parser instanceof FaceBookParser) {
            $this->handleIdsToMigrate($parser);
        }

        $historique = $this->explorationHandler->stop($parser->getNomData());
        $this->em->persist($historique);
        $this->em->flush();

        Monitor::writeln('');
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS'         => $this->explorationHandler->getNbInserts(),
            'UPDATES'      => $this->explorationHandler->getNbUpdates(),
            'BLACKLISTS'   => $this->explorationHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->explorationHandler->getNbExplorations(),
        ]);

        return $events;
    }

    /**
     * @param array $events
     *
     * @return array
     */
    public function handleMany(array $events)
    {
        $this->explorationHandler->start();

        if (!count($events)) {
            return [];
        }
        $this->loadSites();
        $this->loadVilles();
        $this->loadExplorations($events);
        $this->doFilter($events);
        $this->flushExplorations();

        $allowedEvents    = $this->getAllowedEvents($events);
        $notAllowedEvents = $this->getNotAllowedEvents($events);
        unset($events);

        $nbNotAllowedEvents = count($notAllowedEvents);
        for ($i = 0; $i < $nbNotAllowedEvents; ++$i) {
            $this->explorationHandler->addBlackList();
        }

        return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents);
    }

    protected function handleIdsToMigrate(FaceBookParser $parser)
    {
        $ids = $parser->getIdsToMigrate();

        if (!count($ids)) {
            return;
        }

        $eventOwners = $this->repoAgenda->findBy([
            'facebookOwnerId' => array_keys($ids),
        ]);

        $events = $this->repoAgenda->findBy([
            'facebookEventId' => array_keys($ids),
        ]);

        $events = array_merge($events, $eventOwners);
        foreach ($events as $event) {
            if (isset($ids[$event->getFacebookEventId()])) {
                $event->setFacebookEventId($ids[$event->getFacebookEventId()]);
            }

            if (isset($ids[$event->getFacebookOwnerId()])) {
                $event->setFacebookOwnerId($ids[$event->getFacebookOwnerId()]);
            }
            $this->em->persist($event);
        }

        $places = $this->repoPlace->findBy([
           'facebookId' => array_keys($ids),
        ]);

        foreach ($places as $place) {
            if (isset($ids[$place->getFacebookId()])) {
                $place->setFacebookId($ids[$place->getFacebookId()]);
            }
            $this->em->persist($place);
        }

        $this->em->flush();
    }

    protected function getAllowedEvents(array $events)
    {
        $events = array_filter($events, [$this->firewall, 'isValid']);
        usort($events, function (Agenda $a, Agenda $b) {
            if ($a->getSite() == $b->getSite()) {
                return 0;
            }

            return $a->getSite()->getId() - $b->getSite()->getId();
        });

        return $events;
    }

    protected function getNotAllowedEvents(array $events)
    {
        return array_filter($events, function ($event) {
            return !$this->firewall->isValid($event);
        });
    }

    protected function getChunks(array $events)
    {
        $chunks = [];
        foreach ($events as $i => $event) {
            $chunks[$event->getSite()->getId()][$i] = $event;
        }

        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = array_chunk($chunk, self::BATCH_SIZE, true);
        }

        return $chunks;
    }

    protected function unChunk(array $chunks)
    {
        $flat = [];
        foreach ($chunks as $chunk) {
            $flat = array_merge($flat, $chunk);
        }

        return $flat;
    }

    protected function mergeWithDatabase(array $events)
    {
        Monitor::createProgressBar(count($events));

        $chunks = $this->getChunks($events);
        foreach ($chunks as $chunk) {
            //Par site
            $this->echantillonHandler->prefetchPlaceEchantillons($this->unChunk($chunk));

            //Par n éléments
            foreach ($chunk as $currentEvents) {
                $this->echantillonHandler->prefetchEventEchantillons($currentEvents);
                foreach ($currentEvents as $i => $event) {
                    /**
                     * @var Agenda
                     */
                    $echantillonPlaces = $this->echantillonHandler->getPlaceEchantillons($event->getPlace());
                    $echantillonEvents = $this->echantillonHandler->getEventEchantillons($event);

                    $oldUser = $event->getUser();
                    $event   = $this->handler->handle($echantillonEvents, $echantillonPlaces, $event);
                    $this->firewall->filterEventIntegrity($event, $oldUser);
                    if (!$this->firewall->isValid($event)) {
                        $this->explorationHandler->addBlackList();
                    } else {
                        Monitor::advanceProgressBar();
                        $event = $this->em->merge($event);
                        $this->echantillonHandler->addNewEvent($event);
                        if ($this->firewall->isPersisted($event)) {
                            $this->explorationHandler->addUpdate();
                        } else {
                            $this->explorationHandler->addInsert();
                        }
                    }
                    $events[$i] = $event;
                }
                $this->flushEvents();
                $this->firewall->deleteCache();
            }
            $this->flushPlaces();
        }
        Monitor::finishProgressBar();

        return $events;
    }

    protected function flushPlaces()
    {
        $this->em->clear(Place::class);

        $this->echantillonHandler->flushPlaces();
    }

    protected function flushEvents()
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            Monitor::writeln(sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
        }

        $this->em->clear(Agenda::class);

        $this->echantillonHandler->flushEvents();
    }

    protected function loadVilles()
    {
        $villes = $this->em->getRepository('TBNAgendaBundle:Place')->findAllVilles();
        foreach ($villes as $ville) {
            $key                = $this->firewall->getVilleHash($ville['ville']);
            $this->villes[$key] = $ville['id'];
        }
    }

    protected function loadSites()
    {
        $sites = $this->em->getRepository('TBNMainBundle:Site')->findAll();
        foreach ($sites as $site) {
            $key               = $this->firewall->getVilleHash($site->getNom());
            $this->sites[$key] = $site;
        }
    }

    protected function loadExplorations(array $events)
    {
        $fb_ids = $this->getExplorationsFBIds($events);

        if (count($fb_ids)) {
            $this->firewall->loadExplorations($fb_ids);
        }
    }

    protected function flushExplorations()
    {
        $explorations = $this->firewall->getExplorations();

        $batchSize = 100;
        $nbBatches = ceil(count($explorations) / $batchSize);

        for ($i = 0; $i < $nbBatches; ++$i) {
            $currentExplorations = array_slice($explorations, $i * $batchSize, $batchSize);
            foreach ($currentExplorations as $exploration) {
                $exploration->setReason($exploration->getReject()->getReason());
                $this->explorationHandler->addExploration();
                $this->em->persist($exploration);
            }
            $this->em->flush();
        }
        $this->em->clear(Exploration::class);
        $this->firewall->flushExplorations();
    }

    protected function doFilter(array $events)
    {
        foreach ($events as $event) {
            /*
             * @var Agenda $event
             */
            $event->setReject(new Reject());

            if ($event->getPlace()) {
                $event->getPlace()->setReject(new Reject());
            }

            if ($event->getFacebookEventId()) {
                $exploration = $this->firewall->getExploration($event->getFacebookEventId());

                //Une exploration a déjà eu lieu
                if ($exploration) {
                    $this->firewall->filterEventExploration($exploration, $event);
                    $reject = $exploration->getReject();

                    //Celle-ci a déjà conduit à l'élimination de l'événement
                    if (!$reject->isValid()) {
                        $event->getReject()->setReason($reject->getReason());
                        continue;
                    }
                }
            }

            //Même algorithme pour le lieu
            if ($event->getPlace() && $event->getPlace()->getFacebookId()) {
                $exploration = $this->firewall->getExploration($event->getPlace()->getFacebookId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration) && !$exploration->getReject()->isValid()) {
                    $event->getReject()->addReason($exploration->getReject()->getReason());
                    $event->getPlace()->getReject()->setReason($exploration->getReject()->getReason());
                    continue;
                }
            }

            $this->firewall->filterEvent($event);
            if ($this->firewall->isValid($event)) {
                $this->guessEventSite($event);
                $this->firewall->filterEventSite($event);
                $this->handler->cleanEvent($event);
            }
        }
    }

    protected function guessEventSite(Agenda $event)
    {
        $key = $this->firewall->getVilleHash($event->getPlace()->getVille());

        $site = null;
        if (isset($this->sites[$key])) {
            $site = $this->em->getReference(Site::class, $this->sites[$key]->getId());
        } elseif (isset($this->villes[$key])) {
            $site = $this->em->getReference(Site::class, $this->villes[$key]);
        } else {
            foreach ($this->sites as $testSite) {
                if ($this->firewall->isLocationBounded($event->getPlace(), $testSite)) {
                    $site = $this->em->getReference(Site::class, $testSite->getId());
                    break;
                } elseif ($this->firewall->isLocationBounded($event, $testSite)) {
                    $site = $this->em->getReference(Site::class, $testSite->getId());
                    break;
                }
            }
        }

        if ($site) {
            $event->setSite($site);
            $event->getPlace()->setSite($site);
        }
    }

    protected function getExplorationsFBIds(array $events)
    {
        $fbIds = [];
        foreach ($events as $event) {
            /*
             * @var Agenda
             */
            if ($event->getFacebookEventId()) {
                $fbIds[$event->getFacebookEventId()] = true;
            }

            if ($event->getPlace() && $event->getPlace()->getFacebookId()) {
                $fbIds[$event->getPlace()->getFacebookId()] = true;
            }
        }

        return array_keys($fbIds);
    }
}
