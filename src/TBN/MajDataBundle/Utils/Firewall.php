<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Ville;
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
    
    public function loadExplorations()
    {
        $explorations = $this->repoExploration->findAll();
        foreach($explorations as $exploration){
            $this->addExploration($exploration->getSite(), $exploration->getFacebookId(), $exploration);
        }
    }

    public function isPersisted($object)
    {
        return ($object !== null && $object->getId() !== null);
    }

    public function isGoodEvent(Agenda $agenda)
    {
	$isGoodEvent = $this->checkEvent($agenda);
        
	//Vérification supplémentaire de la validité géographique du lieux déclaré de l'event
	if(!$agenda->isTrustedLocation() && $isGoodEvent)
	{
	    $place  = $agenda->getPlace();

	    $isGoodLocation = $place && $this->isLocationBounded($place);
	    if(!$isGoodLocation)
	    {
		var_dump(sprintf('%s en [%f, %f] <error>refusay</error> !', $place ? $place->getNom() : '?', $place ? $place->getLatitude() : '?', $place ? $place->getLongitude() : '?'));
	    }else
	    {
		var_dump(sprintf('%s en [%f, %f] <info>acceptay</info> !', $place ? $place->getNom() : '?', $place ? $place->getLatitude() : '?', $place ? $place->getLongitude() : '?'));
	    }
	    
	    $isGoodEvent = $isGoodLocation;
	}
        
        //Observation de l'exploration
        $fbId = $agenda->getFacebookEventId();
        if($fbId)
        {
            $site = $agenda->getSite();
            $exploration = $this->getExploration($fbId, $site);
            if(null === $exploration)
            {
                $exploration = (new Exploration)
                        ->setFacebookId($fbId)
                        ->setLastUpdated($agenda->getDateModification())
                        ->setSite($site);
                $this->addExploration($site, $fbId, $exploration, true);
            }

            $exploration->setBlackListed(! $isGoodEvent);
        }
        
        return $isGoodEvent;
    }

    public function isPreciseLocation(Place $place) {
	return $place->getLatitude() && $place->getLongitude() && $place->getNom();
    }

    public function isLocationBounded(Place $place)
    {
	$site = $place->getSite();
	return $site &&
                $this->isPreciseLocation($place) &&
                abs($place->getLatitude() - $site->getLatitude()) <= $site->getDistanceMax() &&
		abs($place->getLongitude() - $site->getLongitude()) <= $site->getDistanceMax();
    }

    protected function checkEvent(Agenda $agenda)
    {
	return ($this->checkMinLengthValidity($agenda->getNom(), 3) &&
		$this->checkMinLengthValidity($agenda->getDescriptif(), 20) &&
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
        
        return $this->checkMinLengthValidity($place->getNom(), 2);
    }

    public function isGoodVille(Ville $ville = null)
    {
        if($ville === null)
        {
            return false;
        }
        
        $codePostal = $this->comparator->sanitizeNumber($ville->getCodePostal());
        return   $this->checkMinLengthValidity($ville->getNom(), 2) &&
                    ($this->checkLengthValidity($codePostal, 0) ||
                     $this->checkLengthValidity($codePostal, 5));
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

        $this->toSaveExplorations[] = $this->explorations[$key];
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
	return strlen($this->comparator->sanitize($str)) >= $min;
    }
    
    public function addExploration(Site $site, $fbId, Exploration $exploration, $isNewEntity = false)
    {
        $key = $fbId.'.'.$site->getId();
        $this->explorations[$key] = $exploration;
        
        if($isNewEntity)
        {
            $this->toSaveExplorations[] = $this->explorations[$key];
        }
    }

    public function getExplorations() {
	return $this->explorations;
    }
    
    public function getExplorationsToSave()
    {
        return $this->toSaveExplorations;
    }
}
