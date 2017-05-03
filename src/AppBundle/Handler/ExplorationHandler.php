<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 01/12/2016
 * Time: 20:32
 */

namespace TBN\MajDataBundle\Handler;


use TBN\MajDataBundle\Entity\HistoriqueMaj;

class ExplorationHandler
{
    private $stats;
    private $historique;

    public function __construct() {
        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0
        ];

        $this->historique = null;
    }

    public function addExploration() {
        return $this->add('nbExplorations');
    }

    public function addUpdate() {
        return $this->add('nbUpdates');
    }

    public function addInsert() {
        return $this->add('nbInserts');
    }

    public function addBlackList() {
        return $this->add('nbBlacklists');
    }

    protected function add($key) {
        $this->stats[$key]++;
    }

    public function getNbExplorations() {
        return $this->stats['nbExplorations'];
    }

    public function getNbUpdates() {
        return $this->stats['nbUpdates'];
    }

    public function getNbInserts() {
        return $this->stats['nbInserts'];
    }

    public function getNbBlackLists() {
        return $this->stats['nbBlacklists'];
    }

    /**
     * @return HistoriqueMaj
     */
    public function getHistorique() {
        return $this->historique;
    }

    /**
     * @param string $parserName
     * @return HistoriqueMaj
     */
    public function stop($parserName) {

        $this->historique
            ->setDateFin(new \DateTime)
            ->setExplorations($this->getNbExplorations() + $this->getNbBlackLists())
            ->setNouvellesSoirees($this->getNbInserts())
            ->setUpdateSoirees($this->getNbUpdates())
            ->setFromData($parserName)
        ;

        return $this->getHistorique();
    }

    public function start() {
        $this->historique = (new HistoriqueMaj())
            ->setDateDebut(new \DateTime)
        ;
    }
}
