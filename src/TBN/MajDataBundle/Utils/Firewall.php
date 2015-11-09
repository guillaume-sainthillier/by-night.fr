<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Entity\Exploration;

use Doctrine\Common\Cache\Cache;
use Doctrine\Bundle\DoctrineBundle\Registry;

/**
 * Description of Firewall
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Firewall {

    protected $toSaveExplorations;
    protected $explorations;
    protected $fbExploration;
    protected $comparator;
    protected $om;
    protected $repoExploration;
    protected $cache;

    public function __construct(Cache $cache, Registry $doctrine, Comparator $comparator)
    {
        $this->cache		= $cache;
        $this->om		= $doctrine->getManager();
        $this->repoExploration	= $this->om->getRepository('TBNMajDataBundle:Exploration');
        $this->comparator	= $comparator;
        $this->toSaveExplorations = [];
        $this->explorations	= [];
    }
    
    public function loadExplorations(Site $site)
    {
        $explorations = $this->repoExploration->findBy([
            'site' => $site
        ]);
        foreach($explorations as $exploration){
            $this->addExploration($exploration, false);
        }
    }

    public function isPersisted($object)
    {
        return ($object !== null && $object->getId() !== null);
    }

    public function isGoodEvent(Agenda &$agenda)
    {
	$isGoodEvent = $this->checkEvent($agenda);
        $site = $agenda->getSite();
        
	//Vérification supplémentaire de la validité géographique du lieux déclaré de l'event
	if($agenda->isTrustedLocation() === false && $isGoodEvent)
	{
	    $place  = $agenda->getPlace();

	    $isGoodLocation = $place && $this->isLocationBounded($place);
            
            //Observation du lieu
            if($place && $place->getFacebookId()) {
                $explorationPlace = $this->getExploration($place->getFacebookId(), $site);
                if(null === $explorationPlace)
                {
                    $explorationPlace = (new Exploration)
                            ->setFacebookId($place->getFacebookId())
                            ->setSite($site);
                }
                
                $explorationPlace->setBlackListed(! $isGoodLocation);
                //Ajout ou update
                $this->addExploration($explorationPlace);
            }
	    
	    $isGoodEvent = $isGoodLocation;
	}
        
        //Observation de l'exploration
        $fbId = $agenda->getFacebookEventId();
        if($fbId)
        {
            
            $exploration = $this->getExploration($fbId, $site);
            if(null === $exploration)
            {
                $exploration = (new Exploration)
                        ->setFacebookId($fbId)
                        ->setSite($site);
            }
            $exploration->setBlackListed(! $isGoodEvent)
                    ->setLastUpdated($agenda->getFbDateModification());
                        
            //Ajout ou update
            $this->addExploration($exploration);
        }
        
        return $isGoodEvent;
    }

    public function isPreciseLocation(Place &$place) {
	return $place->getLatitude() && $place->getLongitude() && $place->getNom();
    }

    public function isLocationBounded(Place &$place)
    {
	$site = $place->getSite();
	return $site &&
                $this->isPreciseLocation($place) &&
                abs($place->getLatitude() - $site->getLatitude()) <= $site->getDistanceMax() &&
		abs($place->getLongitude() - $site->getLongitude()) <= $site->getDistanceMax();
    }

    protected function checkEvent(Agenda &$agenda)
    {
	return ($this->checkMinLengthValidity($agenda->getNom(), 3) &&
		($agenda->isTrustedLocation() === true || $this->checkMinLengthValidity($agenda->getDescriptif(), 20)) &&
                ! $this->isSPAMContent($agenda->getDescriptif()) &&
                $agenda->getDateDebut() instanceof \DateTime &&
		($agenda->getDateFin() === null || $agenda->getDateFin() instanceof \DateTime));
    }

    public function isGoodPlace(Place $place = null)
    {
        if($place === null)
        {
            return false;
        }
        
        $codePostal = $this->comparator->sanitizeNumber($place->getCodePostal());
        
        return $this->checkMinLengthValidity($place->getNom(), 2) &&
            $this->checkMinLengthValidity($place->getVille(), 2) &&
            ($this->checkLengthValidity($codePostal, 0) ||
                     $this->checkLengthValidity($codePostal, 5))
        ;
    }

    /**
     *
     * @param int $fbId
     * @param Site $site
     * @return Exploration
     */
    public function getExploration($fbId, Site $site)
    {
	$key = $fbId.'.'.$site->getId();
	if(! isset($this->explorations[$key]))
	{
	    return null;
	}
        
	return $this->explorations[$key];
    }

    private function isSPAMContent($content)
    {
        $black_list = [
	    "Buy && sell tickets at","Please join","Invite Friends","Buy Tickets",
	    "Find Local Concerts", "reverbnation.com", "pastaparty.com", "evrd.us",
	    "farishams.com", "tinyurl.com", "bandcamp.com", "ty-segall.com",
	    "fritzkalkbrenner.com", "campusfm.fr", "polyamour.info", "parislanuit.fr",
	    "Please find the agenda", "Fore More Details like our Page & Massage us"
	];

        $filter = array_filter($black_list, function($elem) use($content)
        {
            return strstr($content, $elem);
        });

        return count($filter) > 0;
    }

    private function checkLengthValidity($str, $length)
    {
	return strlen($this->comparator->sanitize($str)) === $length;
    }

    public function checkMinLengthValidity($str, $min)
    {
	return isset($this->comparator->sanitize($str)[$min]);
    }
    
    public function addExploration(Exploration $exploration, $isNewEntity = true)
    {
        $key = $exploration->getFacebookId().'.'.$exploration->getSite()->getId();
        $this->explorations[$key] = $exploration;
        
        if($isNewEntity) {
            $this->toSaveExplorations[$key] = $this->explorations[$key];
        }        
    }
    
    public function flushNewExplorations()
    {
        unset($this->toSaveExplorations);
        $this->toSaveExplorations = [];
    }

    public function getExplorations() {
	return $this->explorations;
    }
    
    public function getExplorationsToSave()
    {
        return $this->toSaveExplorations;
    }
}
