<?php

namespace TBN\MajDataBundle\Utils;

use Ivory\GoogleMap\Services\Geocoding\Geocoder;
use Doctrine\Common\Cache\Cache;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\MajDataBundle\Utils\Cleaner;
use TBN\MajDataBundle\Utils\Comparator;
use TBN\MajDataBundle\Utils\Merger;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;


/**
 * Description of EventHandler
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class EventHandler
{

    private $geocoder;
    private $firewall;
    private $cleaner;
    private $comparator;
    private $merger;
    private $cache;

    public function __construct(Cache $cache, Geocoder $geocoder, Firewall $firewall, Cleaner $cleaner, Comparator $comparator, Merger $merger)
    {
        $this->cache    = $cache;
        $this->geocoder = $geocoder;
        $this->firewall = $firewall;
        $this->cleaner = $cleaner;
        $this->comparator = $comparator;
        $this->merger = $merger;
    }

    public function downloadImage(Agenda &$agenda)
    {
        //$url = preg_replace('/([^:])(\/{2,})/', '$1/', $agenda->getUrl());
        $url = $agenda->getUrl();
        $agenda->setUrl(null)->setPath(null);

        try {
            $image = file_get_contents($url);
        } catch (\Exception $ex) {
            echo $ex->getMessage();
            $image = false;
        }

        if($image !== false)
        {
            //En cas d'url du type:  http://u.rl/image.png?params
            $ext = preg_replace("/\?(.+)/", "", pathinfo($url, PATHINFO_EXTENSION));

            $filename = sha1(uniqid(mt_rand(), true)).".".$ext;
            $pathname = $agenda->getUploadRootDir()."/".$filename;
            $octets = file_put_contents($pathname, $image);

            if($octets !== false)
            {
                $agenda->setPath($filename)->setUrl($url);
            }
        }       
    }

    public function handle(array &$persistedPlaces, Site &$site, Agenda &$agenda)
    {        
        //Assignation du site
        $agenda->setSite($site);

        $tmpPlace = $agenda->getPlace();
        if($tmpPlace !== null) //Analyse de la place
        {
            //Anticipation par traitement du blacklistage de la place;
            if($tmpPlace->getFacebookId()) {
                $exploration = $this->firewall->getExploration($tmpPlace->getFacebookId(), $site);
                if(null !== $exploration && $exploration->getBlackListed() === true) {
                    return null;
                }
            }
            
	    //Recherche d'une meilleure place déjà existante
            $tmpPlace->setSite($site);
            $tmpPlace = $this->cleaner->getCleanedPlace($tmpPlace);
            $place = $this->handlePlace($persistedPlaces, $tmpPlace);
            $agenda->setPlace($place);
        }

        //Nettoyage de l'événement
        return $this->cleaner->cleanEvent($agenda);
    }

    public function handleEvent(array &$persistedEvents, Agenda &$testedAgenda = null)
    {
        if(null !== $testedAgenda && $this->firewall->isGoodEvent($testedAgenda))
        {
            //Evenement persisté
            $bestEvent = $this->comparator->getBestEvent($persistedEvents, $testedAgenda);
            
            //On fusionne l'event existant avec celui découvert (même si NULL)
            return $this->merger->mergeEvent($bestEvent, $testedAgenda);            
        }
        return null;
    }

    public function handlePlace(array &$persistedPlaces, Place &$testedPlace = null)
    {
        if($this->firewall->isGoodPlace($testedPlace))
        {
            $bestPlace = $this->comparator->getBestPlace($persistedPlaces, $testedPlace);

            return $this->merger->mergePlace($bestPlace, $testedPlace);
        }
        
        return null;
    }
}
