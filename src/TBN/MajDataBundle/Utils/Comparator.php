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
        if($a !== null && $b !== null && $a->getSite() === $b->getSite())
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
        if ($a !== null && $b !== null && $a->getSite() === $b->getSite() && $this->getMatchingScoreVille($a->getVille(), $b->getVille()) >= 60)
        {
	    //~ Même ville et même rue
	    if($this->getMatchingScoreRue($a->getRue(), $b->getRue()) >= 100)
	    {
		return 100;
	    }

            if($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 80)
            {
                return 90;
            }

            if($this->isSubInSub($a->getNom(), $b->getNom()))
            {
                return 85;
            }

            if($this->getMatchingScoreText($a->getLatitude(), $b->getLatitude()) === 100 &&
                    $this->getMatchingScoreText($a->getLongitude(), $b->getLongitude()) === 100)
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
        if($this->isSameMoment($a, $b))
        {
            //Fort taux de ressemblance du nom ou descriptif ou égalité de l'id FB
	    if($this->getMatchingScoreText($a->getNom(), $b->getNom()) > 75 ||
		$this->getMatchingScoreHTML($a->getDescriptif(), $b->getDescriptif()) > 75 ||
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

    protected function isSubInSub($str1, $str2)
    {
        $sanitized1 = $this->sanitize($str1);
        $sanitized2 = $this->sanitize($str2);

        return $this->isSubstrInStr($str1, $str2) || $this->isSubstrInStr($str2, $str1) || $this->isSubstrInStr($sanitized1, $sanitized2) || $this->isSubstrInStr($sanitized2, $sanitized1);
    }

    protected function isSubstrInStr($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    protected function getMatchingScore($a, $b)
    {
	$pourcentage = 0;
	if(strlen($a) > 0 && strlen($b) > 0)
	{
	    if($a === $b)
	    {
		return 100;
	    }

	    similar_text($a, $b, $pourcentage);
	}

	return $pourcentage;
    }

    protected function getMatchingScoreRue($a, $b)
    {
	$trimedA = $this->sanitizeRue($a);
	$trimedB = $this->sanitizeRue($b);

	return $this->getMatchingScore($trimedA, $trimedB);
    }
    
    protected function getMatchingScoreHTML($a, $b)
    {
	$trimedA = $this->sanitizeHTML($a);
	$trimedB = $this->sanitizeHTML($b);

	return $this->getMatchingScore($trimedA, $trimedB);
    }

    protected function getMatchingScoreText($a, $b)
    {
	$trimedA = $this->sanitize($a);
	$trimedB = $this->sanitize($b);

	return $this->getMatchingScore($trimedA, $trimedB);
    }

    public function getBestContent($valueA, $valueB)
    {
        if(is_object($valueA) && is_object($valueB))
        {
            return $valueA ?: $valueB;
        }        
        
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

    public function sanitizeRue($string) {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->deleteMultipleSpaces($step2);

        return trim($step3);
    }

    public function sanitizeHTML($string) {
	return $this->sanitize(strip_tags($string));
    }

    public function sanitize($string) {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->replaceNonAlphanumericChars($step2);
        $step4 = $this->util->deleteStopWords($step3);
        $step5 = $this->util->deleteMultipleSpaces($step4);

        return trim($step5);
    }

    private function isSameDate(\DateTime $a, \DateTime $b)
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    private function isSameText($a, $b, $minPourcentage = 100, $equalsAreSame = false)
    {
	$trimedA = $this->sanitize($a);
	$trimedB = $this->sanitize($b);

	if(strlen($trimedA) === 0 || strlen($trimedB) === 0)
	{
	    return $equalsAreSame;
	}elseif($trimedA === $trimedB)
	{
	    return true;
	}else
	{
	    $pourcentage = 0;
	    similar_text($a, $b, $pourcentage);
	    return $pourcentage >= $minPourcentage;
	}
    }
}
