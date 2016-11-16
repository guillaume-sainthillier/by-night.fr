<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\Comparator;

/**
 * Description of Merger
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Merger
{

    private $comparator;

    public function __construct(Comparator $comparator)
    {
        $this->comparator = $comparator;
    }

    public function mergeEvent(Agenda &$a = null, Agenda &$b = null)
    {
        return $this->merge($a, $b, [
            'id',
            'nom',
            'descriptif',
            'horaires',
            'modification_derniere_minute',
            'type_manifestation',
            'categorie_manifestation',
            'theme_manifestation',
            'station_metro_tram',
            'reservation_telephone',
            'reservation_email',
            'reservation_internet',
            'tarif',
            'url',
            'facebook_event_id',
            'facebook_owner_id',
            'fb_participations',
            'fb_interets',
            'fb_post_id',
            'fb_post_system_id',
            'tweet_post_id',
            'tweet_post_system_id',
            'google_post_id',
            'google_system_post_id',
            'source',
            'fb_date_modification',
            'place',
            'user',
            'file'
        ]);
    }

    public function mergePlace(Place &$a = null, Place &$b = null)
    {
        return $this->merge($a, $b, [
            'id',
            'latitude',
            'longitude',
            'rue',
            'url',
            'ville',
            'codePostal',
            'facebook_id'
        ]);
    }

    /**
     * Merge les champs de b dans a s'ils sont jugés plus pertinents
     * @param \stdClass $a
     * @param \stdClass $b
     * @param array $fields
     * @return \stdClass
     */
    private function merge($a, $b, array $fields)
    {
        //Un ou les deux est nul, pas la peine de merger
        if ($a === null || $b === null) {
            return ($a ?: $b); //Retourne l'objet non nul s'il existe
        }

        foreach ($fields as $field) {
            $getter = 'get' . $this->skakeToCamel($field);
            $setter = 'set' . $this->skakeToCamel($field);

            $valueA = $a->$getter();
            $valueB = $b->$getter();
            $value = $this->comparator->getBestContent($valueA, $valueB);

            $a->$setter($value);
        }

        return $a;
    }

    private function skakeToCamel($str)
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $str)));
    }
}
