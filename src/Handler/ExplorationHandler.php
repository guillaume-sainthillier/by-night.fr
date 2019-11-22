<?php

namespace App\Handler;

use App\Entity\HistoriqueMaj;
use DateTime;

class ExplorationHandler
{
    private $stats;

    private $historique;

    public function __construct()
    {
        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0,
        ];

        $this->historique = null;
    }

    public function addExploration()
    {
        return $this->add('nbExplorations');
    }

    protected function add($key)
    {
        if ($this->isStarted()) {
            ++$this->stats[$key];
        }

        return $this;
    }

    public function isStarted()
    {
        return null !== $this->historique;
    }

    public function addUpdate()
    {
        return $this->add('nbUpdates');
    }

    public function addInsert()
    {
        return $this->add('nbInserts');
    }

    public function addBlackList()
    {
        return $this->add('nbBlacklists');
    }

    /**
     * @return HistoriqueMaj
     */
    public function stop()
    {
        $this->getHistorique()
            ->setDateFin(new DateTime())
            ->setExplorations($this->getNbExplorations() + $this->getNbBlackLists())
            ->setNouvellesSoirees($this->getNbInserts())
            ->setUpdateSoirees($this->getNbUpdates())
            ->setFromData('?');

        return $this->historique;
    }

    /**
     * @return HistoriqueMaj
     */
    public function getHistorique()
    {
        return $this->historique;
    }

    public function getNbExplorations()
    {
        return $this->stats['nbExplorations'];
    }

    public function getNbBlackLists()
    {
        return $this->stats['nbBlacklists'];
    }

    public function getNbInserts()
    {
        return $this->stats['nbInserts'];
    }

    public function getNbUpdates()
    {
        return $this->stats['nbUpdates'];
    }

    public function start()
    {
        $this->historique = (new HistoriqueMaj())
            ->setDateDebut(new DateTime());
    }

    public function reset()
    {
        //Call GC
        $this->historique = null;
        $this->stats = null;
        unset($this->historique, $this->stats);

        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0,
        ];
    }
}
