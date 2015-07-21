<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\Comparator;

use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Description of Merger
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Merger {

    private $propertyAccessor;
    private $comparator;
    
    public function __construct(Comparator $comparator)
    {
        $this->comparator = $comparator;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function mergeEvent(Agenda $a = null, Agenda $b = null)
    {
        return $this->merge($a, $b, [
            'nom',
            'descriptif',
            'modification_derniere_minute',
            'type_manifestation',
            'categorie_manifestation',
            'theme_manifestation',
            'station_metro_tram',
            'theme_manifestation',
            'reservation_telephone',
            'reservation_email',
            'reservation_internet',
            'tarif',
            'url',
            'facebook_event_id',
            'fb_post_id',
            'fb_post_system_id',
            'tweet_post_id',
            'tweet_post_system_id',
            'google_post_id',
            'google_system_post_id',
            'source'
        ]);
    }

    public function mergePlace(Place $a = null, Place $b = null)
    {
        return $this->merge($a, $b, [
            'latitude',
            'longitude',
            'rue',
	    'url'
        ]);
    }

    /**
     * Merge b dans a et retourne l'entité mergée
     * @param Ville $a
     * @param Ville $b
     */
    public function mergeVille(Ville $a = null, Ville $b = null)
    {
        return $this->merge($a, $b, [
            'codePostal'
        ]);
    }

    /**
     * Merge les champs de b dans a s'ils sont jugés plus pertinents
     * @param type $a
     * @param type $b
     * @param type $fields
     */
    private function merge($a, $b, $fields)
    {
        //Un ou les deux est nul, pas la peine de merger
        if($a === null || $b === null)
        {
            return ($a?: $b); //Retourne une Ville ou null selon les cas
        }
        
        foreach($fields as $field)
        {
            $valueA = $this->propertyAccessor->getValue($a, $field);
            $valueB = $this->propertyAccessor->getValue($b, $field);
            $value  = $this->comparator->getBestContent($valueA, $valueB);
            
            $this->propertyAccessor->setValue($a, $field, $value);
        }

        return $a;
    }
}
