<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Entity\BlackList;

use Doctrine\Common\Cache\Cache;
use Doctrine\Bundle\DoctrineBundle\Registry;

/**
 * Description of Firewall
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Firewall {

    protected $blackList;
    protected $fbBlackList;
    protected $comparator;
    protected $om;
    protected $repoBlackList;
    protected $cache;
    protected $geocoder;

    public function __construct(Cache $cache, Registry $doctrine, Comparator $comparator)
    {
        $this->cache	    = $cache;
        $this->om	    = $doctrine->getManager();
        $this->repoBlackList= $this->om->getRepository('TBNMajDataBundle:BlackList');
        $this->comparator   = $comparator;
        $this->fbBlackList  = [];
        $this->blackList    = [];
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
	    $place = $agenda->getPlace();

	    $retour = $place && $this->isLocationBounded($place);

	    if(!$retour)
	    {
		var_dump(sprintf('%s en [%f, %f] <error>refusay</error> !', $place ? $place->getNom() : '?', $place ? $place->getLatitude() : '?', $place ? $place->getLongitude() : '?'));
	    }else
	    {
		var_dump(sprintf('%s en [%f, %f] <info>acceptay</info> !', $place ? $place->getNom() : '?', $place ? $place->getLatitude() : '?', $place ? $place->getLongitude() : '?'));
	    }

	    $fbId = $agenda->getFacebookEventId();
	    $site = $agenda->getSite();
	    if(! $retour && $fbId && ! $this->isBlackListed($fbId, $site))
	    {
		$blackList = (new BlackList)
			->setFacebookId($fbId)
			->setReason('Coordonnées non conformes')
			->setSite($site);

		$this->blackList[] = $blackList;
		$this->fbBlackList[$fbId] = true;
	    }

	    return $retour;
	}
	
        return $isGoodEvent;
    }

    public function isPreciseLocation(Place $place) {
	return $place->getLatitude() && $place->getLongitude() && $place->getNom();
    }

    public function isLocationBounded(Place $place)
    {
	$site = $place->getSite();

	if(! $this->isPreciseLocation($place) || !$site)
	{
	    return false;
	}
	
	return (abs($place->getLatitude() - $site->getLatitude()) <= $site->getDistanceMax() &&
		abs($place->getLongitude() - $site->getLongitude()) <= $site->getDistanceMax());
    }

    protected function checkEvent(Agenda $agenda)
    {
	return ($this->checkMinLengthValidity($agenda->getNom(), 3) &&
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

    public function isBlackListed($fbId, Site $site)
    {
	if(! isset($this->fbBlackList[$fbId]))
	{
	    $blackList = $this->repoBlackList->findOneBy(['facebookId' => $fbId, 'site' => $site]);

	    //L'entité est trouvée, l'item est blacklisté
	    $this->fbBlackList[$fbId] = ($blackList !== null);
	}

	return $this->fbBlackList[$fbId];
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
        $key = 'checkLengthValidity.'.md5($str.$length);
        if(! $this->cache->contains($key))
        {
            $retour = strlen($this->comparator->sanitize($str)) === $length;
            $returnSave = $this->cache->save($key, $retour);
            if(! $returnSave)
            {
                return $retour;
            }
        }
        return $this->cache->fetch($key);
    }

    public function checkMinLengthValidity($str, $min)
    {
        $key = 'checkMinLengthValidity.'.md5($str.$min);
        if(! $this->cache->contains($key))
        {
            $retour = strlen($this->comparator->sanitize($str)) >= $min;
            $returnSave = $this->cache->save($key, $retour);

            if(! $returnSave)
            {
                return $retour;
            }
        }
        
        return $this->cache->fetch($key);
    }

    public function getBlackList() {
	return $this->blackList;
    }
}
