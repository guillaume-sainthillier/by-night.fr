<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Ville;
use TBN\AgendaBundle\Entity\Agenda;
use Doctrine\Common\Cache\Cache;
use TBN\MajDataBundle\Utils\Util;

/**
 * 
 * @author guillaume
 */
class Comparator {

    private $cache;
    private $util;

    public function __construct(Util $util, Cache $cache) {
        $this->util = $util;
        $this->cache = $cache;
    }

    public function getBestVille($villes, Ville $testedVille = null, $debug = false)
    {
        $this->debug = $debug;
        return $this->getBest($villes, [$this, 'getMatchingScoreVille'], $testedVille);
    }

    public function getMatchingScoreVille(Ville $a = null, Ville $b = null)
    {
        $pourcentage = 0;
        if($a !== null && $b !== null)
        {
            if($this->getMatchingScoreText($a->getNom(), $b->getNom()) > 80)
            {
                $pourcentage += 80;
            }

            if($this->isSameText($a->getCodePostal(), $b->getCodePostal()))
            {
                $pourcentage += 60;
            }
        }

        return $pourcentage;
    }
    
    public function getBestPlace($places, Place $testedPlace = null, $debug = false)
    {
        $this->debug = $debug;
        return $this->getBest($places, [$this, 'getMatchingScorePlace'], $testedPlace, 60);
    }

    public function getMatchingScorePlace(Place $a = null, Place $b = null)
    {
        if ($a !== null && $b !== null && $a->getSite() === $b->getSite())
        {
            if($this->getMatchingScoreText($a->getNom(), $b->getNom()) > 80)
            {
                return 100;
            }

            if($this->isSubInSub($a->getNom(), $b->getNom(), $a, $b))
            {
                return 85;
            }

            if($this->getMatchingScoreText($a->getRue(), $b->getRue()) > 80)
            {
                return $this->getMatchingScoreVille($a->getVille(), $b->getVille());
            }

            if($this->getMatchingScoreText($a->getLatitude(), $b->getLatitude()) === 100 &&
                    $this->getMatchingScoreText($a->getLongitude(), $b->getLongitude()))
            {
                return 75;
            }
        }

        return 0;
    }

    public function getBestEvent($events, Agenda $testedEvent, $debug = false)
    {
        $this->debug = $debug;
        return $this->getBest($events, [$this, 'getMatchingScoreEvent'], $testedEvent);
    }

    protected function getMatchingScoreEvent(Agenda $a, Agenda $b)
    {
        if(! $this->isSameMoment($a, $b))
        {
            return 0;
        }

        //Fort taux de ressemblance du nom ou descriptif ou égalité de l'id FB
        if($this->getMatchingScoreText($a->getNom(), $b->getNom()) > 75 ||
            $this->getMatchingScoreText($a->getDescriptif(), $b->getDescriptif()) > 75 ||
            $this->getMatchingScoreText($a->getFacebookEventId(), $b->getFacebookEventId()) >= 100)
        {
            return 100;
        }

        if($this->isSubInSub($a->getNom(), $b->getNom()))
        {
            return 85;
        }

        if($this->getMatchingScorePlace($a->getPlace(), $b->getPlace()) >= 75)
        {
            return 80;
        }

        return 0;
    }

    private function getBest($items, $machingFunction, $testedItem = null, $minScore = 75)
    {
        $bestScore = 0;
        $bestItem = null;

        foreach ($items as $item) {
            $score = call_user_func($machingFunction, $item, $testedItem);
            if($score >= 100)
            {
                return $item;
            }elseif($score >= $minScore && $score > $bestScore)
            {
                $bestScore = $score;
                $bestItem = $item;
            }
        }

        return $bestItem;
    }

    protected function isSubInSub($str1, $str2, $itemA = null, $itemB = null)
    {
        $sanitized1 = $this->sanitize($str1);
        $sanitized2 = $this->sanitize($str2);
//        if(strlen($sanitized1) === 0 || strlen($sanitized2) === 0)
//        {
//            return false;
//        }

        return $this->isSubstrInStr($str1, $str2) || $this->isSubstrInStr($str2, $str1);
    }

    protected function isSubstrInStr($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }


    protected function getMatchingScoreText($a, $b)
    {
        $key = 'getMatchingScoreText.'.md5($a.'.'.$b);
        if(! $this->cache->contains($key))
        {
            $trimedA = $this->sanitize($a);
            $trimedB = $this->sanitize($b);

            $pourcentage = 0;
            if(strlen($trimedA) > 0 && strlen($trimedB) > 0)
            {
                similar_text($a, $b, $pourcentage);
            }

            $retourSave = $this->cache->save($key, $pourcentage);
            if(! $retourSave)
            {
                return $pourcentage;
            }
        }

        return $this->cache->fetch($key);
    }

    public function getBestContent($valueA, $valueB)
    {
        $compareA = $this->sanitize($valueA);

        return strlen($compareA) > 0 ? ($valueA?: null) : ($valueB?:null);
    }

    public function sanitizeNumber($string)
    {
        return preg_replace('/\D/', '', $string);
    }

    public function isSameMoment(Agenda $a, Agenda $b)
    {
        $dateDebutA = $a->getDateDebut();
        $dateDebutB = $b->getDateDebut();

        $dateFinA   = $a->getDateFin();
        $dateFinB   = $b->getDateFin();

        if($dateDebutA === false || $dateDebutB === false)
        {
            return false;
        }

        return $this->isSameDate($dateDebutA, $dateDebutB) && $this->isSameDate($dateFinA, $dateFinB);
    }

    

    public function sanitize($string) {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->replaceNonAlphanumericChars($step2);
        $step4 = $this->util->deleteMultipleSpaces($step3);

        return trim($step4);
    }

    private function isSameDate(\DateTime $a, \DateTime $b)
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    private function replaceAccents($string) {
        return str_replace(array('à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'), array('a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'A', 'A', 'A', 'A', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'N', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y'), $string);
    }

    private function isSameText($a, $b, $minPourcentage = 100, $equalsAreSame = false)
    {
        $key = 'isSameText.'.md5($a.'.'.$b.($equalsAreSame ? '1' : '0'));
        if(! $this->cache->contains($key))
        {
            $trimedA = $this->sanitize($a);
            $trimedB = $this->sanitize($b);

            if(strlen($trimedA) === 0 || strlen($trimedB) === 0)
            {
                $retour = $equalsAreSame;
            }else
            {
                $pourcentage = 0;
                similar_text($a, $b, $pourcentage);
                $retour = $trimedA === $trimedB || $pourcentage >= $minPourcentage;
            }

            $retourSave = $this->cache->save($key, $retour);
            if(! $retourSave)
            {
                return $retour;
            }
        }

        return $this->cache->fetch($key);        
    }
}
