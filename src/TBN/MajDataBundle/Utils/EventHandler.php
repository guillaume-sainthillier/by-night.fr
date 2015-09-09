<?php

namespace TBN\MajDataBundle\Utils;

use Ivory\GoogleMap\Services\Geocoding\Geocoder;
use Doctrine\Common\Cache\Cache;
use TBN\MajDataBundle\Utils\Firewall;
use TBN\MajDataBundle\Utils\Cleaner;
use TBN\MajDataBundle\Utils\Comparator;
use TBN\MajDataBundle\Utils\Merger;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;
use Ivory\GoogleMap\Services\Geocoding\Result\GeocoderResult;


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

    public function downloadImage(Agenda $agenda)
    {
        //$url = preg_replace('/([^:])(\/{2,})/', '$1/', $agenda->getUrl());
        $url = $agenda->getUrl();
        $agenda->setUrl(null)->setPath(null);

        try {
            $image = file_get_contents($url);
        } catch (\Exception $ex) {
            var_dump($ex->getMessage(), $ex->getLine());
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

    private function googleMapResultToPlace(GeocoderResult $result)
    {
	$place = new Place;

	$numRue = null;
	foreach ($result->getAddressComponents('street_number') as $addressComponent) {
	    $numRue = $addressComponent->getLongName();
	}

	foreach ($result->getAddressComponents('route') as $addressComponent) {
	    $place->getRue($numRue . " " . $addressComponent->getLongName());
	}

	foreach ($result->getAddressComponents('locality') as $addressComponent) {
	    $place->getVille()->setNom($addressComponent->getLongName());
	}

	foreach ($result->getAddressComponents('postal_code') as $addressComponent) {
	    $place->getVille()->setCodePostal($addressComponent->getLongName());
	}

	$location = $result->getGeometry()->getLocation();
	$place->setLatitude($location->getLatitude())->setLongitude($location->getLongitude());

	return $place;
    }

    private function guessPlacesFromLocation($nom)
    {
        $places = [];
        
        if($nom)
	{
            $key = $nom;
            if(! $this->cache->contains($key))
            {
                var_dump('Je cherche', $nom);
                $geocoder = $this->geocoder;
                $response = $geocoder->geocode($nom);
                $status = $response->getStatus();

                if ($status === 'OK')
                {
                    $results = $response->getResults();
                    foreach ($results as $result) {
                        if (!$result->isPartialMatch()) { //L'adresse a été trouvée précisément
                            $places[] = $this->googleMapResultToPlace($result);
                        }
                    }
                }
                
                $this->cache->save($key, $places);
            }
            
            return $this->cache->fetch($key);
	}
        
	return $places;
    }

    public function handle(&$persistedPlaces, &$persistedVilles, Site &$site, Agenda &$agenda)
    {
        $start = microtime(true);
        //Assignation du site
        $agenda->setSite($site);

        $tmpPlace = $agenda->getPlace();
        if($tmpPlace !== null) //Analyse de la place
        {
            $end = microtime(true);
            var_dump('FLAG0.0 : '.(($end - $start)*1000).' ms');
            $start = microtime(true);
	    $tmpPlace->setSite($site);
	    $tmpVille = $tmpPlace->getVille();

	    //Source non fiable + lieu imprécis -> geocoding
	    if(! $agenda->isTrustedLocation() && !$this->firewall->isPreciseLocation($tmpPlace))
	    {
                $end = microtime(true);
                var_dump('FLAG0.1 : '.(($end - $start)*1000).' ms');
                $start = microtime(true);
                try {
                    $candidatesPlaces = $this->guessPlacesFromLocation($tmpPlace->getNom());
                } catch (\Ivory\GoogleMap\Exception\Exception $ex) {
                    $candidatesPlaces = [];
                }
		
                $end = microtime(true);
                var_dump('FLAG0.2 : '.(($end - $start)*1000).' ms');
                $start = microtime(true);
		$filteredCandidatesPlaces = array_filter($candidatesPlaces, [$this->firewall, 'isLocationBounded']);
		foreach($filteredCandidatesPlaces as $filteredCandidatesPlace)
		{
		    $tmpPlace = $filteredCandidatesPlace;
		    $tmpVille = $tmpPlace->getVille();
		}
                $end = microtime(true);
                var_dump('FLAG0.4 : '.(($end - $start)*1000).' ms');
                $start = microtime(true);
	    }
            
            if($tmpVille !== null)
            {
                $end = microtime(true);
                var_dump('FLAG0.5 : '.(($end - $start)*1000).' ms');
                $start = microtime(true);
		//Recherche d'une meilleure ville déjà existante
                $tmpVille->setSite($site);
                $ville = $this->handleVille($persistedVilles, $this->cleaner->getCleanedVille($tmpVille));
                $tmpPlace->setVille($ville);
                $end = microtime(true);
                var_dump('FLAG0.6 : '.(($end - $start)*1000).' ms');
                $start = microtime(true);
            }

            $end = microtime(true);
            var_dump('FLAG0.7 : '.(($end - $start)*1000).' ms');
            $start = microtime(true);
	    //Recherche d'une meilleure place déjà existante	    
            $place = $this->handlePlace($persistedPlaces, $this->cleaner->getCleanedPlace($tmpPlace));
            $agenda->setPlace($place);
            $end = microtime(true);
            var_dump('FLAG0.8 : '.(($end - $start)*1000).' ms');
            $start = microtime(true);
        }

        //Nettoyage de l'événement
        return $this->cleaner->getCleanedEvent($agenda);
    }

    public function handleEvent(&$persistedEvents, Agenda $testedAgenda = null)
    {
        if(null === $testedAgenda || ! $this->firewall->isGoodEvent($testedAgenda))
        {
            return null;
        }

        $bestEvent = $this->comparator->getBestEvent($persistedEvents, $testedAgenda);

        //Pas d'événement existant trouvé
        if($bestEvent === null && $testedAgenda !== null)
        {
            $persistedEvents[] = $testedAgenda;
            return $testedAgenda;
        }

        //On fusionne l'event existant avec celui découvert
        return $this->merger->mergeEvent($bestEvent, $testedAgenda);
    }

    public function handleVille(&$persistedVilles, Ville $testedVille = null)
    {
        if(! $this->firewall->isGoodVille($testedVille))
        {
            return null;
        }

        $bestVille = $this->comparator->getBestVille($persistedVilles, $testedVille);

        //Pas de place existante trouvée
        if($bestVille === null && $testedVille !== null)
        {
            $persistedVilles[] = $testedVille;
            return $testedVille;
        }

        //On fusionne la place existante avec celle découverte
        return $this->merger->mergeVille($bestVille, $testedVille);
    }

    public function handlePlace(&$persistedPlaces, Place $testedPlace = null)
    {        
        if(! $this->firewall->isGoodPlace($testedPlace))
        {
            return null;
        }

        $bestPlace = $this->comparator->getBestPlace($persistedPlaces, $testedPlace);

        //Pas de place existante trouvée
        if($bestPlace === null && $testedPlace !== null)
        {
            $persistedPlaces[] = $testedPlace;
            return $testedPlace;
        }

        return $this->merger->mergePlace($bestPlace, $testedPlace);
    }
}
