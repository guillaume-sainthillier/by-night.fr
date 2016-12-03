<?php

namespace TBN\MajDataBundle\Utils;

use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use Doctrine\Common\Cache\Cache;

/**
 *
 * @author guillaume
 */
class Comparator
{

    private $cache;
    private $util;

    public function __construct(Util $util, Cache $cache)
    {
        $this->util = $util;
        $this->cache = $cache;
    }

    public function getMatchingScoreVille(Place &$a = null, Place &$b = null)
    {
        $pourcentage = 0;
        if ($a !== null && $b !== null) {
            $isMatchingCP = ($a->getCodePostal() && $b->getCodePostal()) ? $this->isSameText($a->getCodePostal(), $b->getCodePostal()) : false;
            //Même CP -> fortement identiques
            if ($isMatchingCP) {
                return 100;
            }

            $matchingNom = ($a->getVille() && $b->getVille()) ? $this->getMatchingScoreText($a->getVille(), $b->getVille()) : 0;

            //Même Nom et pas de CP sur l'une des villes -> fortement identiques
            if ($matchingNom >= 80 && (!$a->getCodePostal() || !$b->getCodePostal())) {
                return 80;
            } elseif ($matchingNom >= 80) {
                return 75;
            }
        }

        return $pourcentage;
    }

    public function getBestPlace(array &$places, Place &$testedPlace = null)
    {
        return $this->getBest('place', $places, 'getMatchingScorePlace', $testedPlace, 80);
    }

    public function getMatchingScorePlace(Place $a = null, Place $b = null)
    {
        if ($a !== null && $b !== null) {
            if($this->getStrictMatchingPlace($a, $b)) {
                return 100;
            }

            if ($this->getMatchingScoreVille($a, $b) >= 80) {

                //~ Même ville et même rue
                $hasRueNumberA = preg_match("#^(\d+)#", $a->getRue());
                $hasRueNumberB = preg_match("#^(\d+)#", $b->getRue());
                $hasSameComparison = ($hasRueNumberA && $hasRueNumberB) || (! $hasRueNumberA && ! $hasRueNumberB);
                if ($hasSameComparison && $this->getMatchingScoreRue($a->getRue(), $b->getRue()) >= 100) {
                    if ($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 80) {
                        return 100;
                    }
                }

                if ($this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 80) {
                    return 90;
                }

                if ($this->isSubInSub($a->getNom(), $b->getNom())) {
                    return 85;
                }
            }
        }

        return 0;
    }

    protected function getStrictMatchingEvent(Agenda &$a, Agenda &$b)
    {
        return ($a->getFacebookEventId() && $a->getFacebookEventId() == $b->getFacebookEventId()) ||
        ($a->getId() && $a->getId() == $b->getId()) ||
        $this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 75 ||
        $this->getMatchingScoreHTML($a->getDescriptif(), $b->getDescriptif()) >= 75;
    }

    protected function getStrictMatchingPlace(Place &$a, Place &$b)
    {
        return ($a->getFacebookId() && $a->getFacebookId() == $b->getFacebookId()) ||
        ($a->getId() && $a->getId() == $b->getId());
    }

    public function getBestEvent(array $events, Agenda $testedEvent)
    {
        return $this->getBest('agenda', $events, 'getMatchingScoreEvent', $testedEvent);
    }

    protected function getMatchingScoreEvent(Agenda $a, Agenda $b)
    {
//        if ($this->isSameMoment($a, $b)) {
            //Fort taux de ressemblance du nom ou descriptif ou égalité de l'id FB
            if (($a->getFacebookEventId() !== null && $a->getFacebookEventId() == $b->getFacebookEventId()) ||
                $this->getMatchingScoreText($a->getNom(), $b->getNom()) >= 75 ||
                $this->getMatchingScoreHTML($a->getDescriptif(), $b->getDescriptif()) >= 75
            ) {
                return 100;
            }

            if ($this->isSubInSub($a->getNom(), $b->getNom())) {
                return 85;
            }

            $placeA = $a->getPlace();
            $placeB = $b->getPlace();
            if ($this->getMatchingScorePlace($placeA, $placeB) >= 75) {
                return 80;
            }
//        }

        return 0;
    }

    private function getBest($keyPrefix, array $items, $machingFunction, $testedItem = null, $minScore = 75)
    {
        if (null === $testedItem) {
            return null;
        }
//
//        $hashId = spl_object_hash($testedItem);
//        if ($testedItem->getId() !== null && isset($items[$testedItem->getId()])) {
//            return $items[$testedItem->getId()];
//        } elseif ($testedItem->getId() === null && isset($items[$hashId])) {
//            return $items[$hashId];
//        }

        $bestScore = 0;
        $bestItem = null;

//        $hashA = md5(json_encode($testedItem->toArray()));
        foreach ($items as $item) {
//            $score = null;

//            $hashB = md5(json_encode($item->toArray()));
//            $keys = ['getBest.' . $keyPrefix . '.' . $hashA . '.' . $hashB, 'getBest.' . $keyPrefix . '.' . $hashB . '.' . $hashA];
//            foreach ($keys as $key) {
//                if ($this->cache->contains($key)) {
//                    $score = $this->cache->fetch($key);
//                    break;
//                }
//            }

//            if (null === $score) {
                $score = $this->$machingFunction($item, $testedItem);
//                $this->cache->save($keys[0], $score);
//                $this->cache->save($keys[1], $score);
//            }

            if ($score >= 100) {
                return $item;
            } elseif ($score >= $minScore && $score > $bestScore) {
                $bestItem = $item;
                $bestScore = $score;
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
        // = strlen > 0
        if (isset($a[0]) && isset($b[0]) > 0) {
            if ($a === $b) {
                return 100;
            }

            if (isset($a[250]) || isset($b[250])) {
                similar_text($a, $b, $pourcentage);
            } else {
                try {
                    $pourcentage = $this->getDiffPourcentage($a, $b);
                } catch (\Exception $ex) {
                }
            }
        }

        return $pourcentage;
    }

    private function getDiffPourcentage($a, $b)
    {
        $hashA = md5($a);
        $hashB = md5($b);
        $keys = ['getDiffPourcentage.' . $hashA . '.' . $hashB, 'getDiffPourcentage.' . $hashB . '.' . $hashA];

        foreach ($keys as $key) {
            if ($this->cache->contains($key)) {
                return $this->cache->fetch($key);
            }
        }

        $score = (1 - levenshtein($a, $b) / max(strlen($a), strlen($b))) * 100;

        $this->cache->save($keys[0], $score);

        return $score;
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

    public function sanitizeNumber($string)
    {
        return preg_replace('/\D/', '', $string);
    }

    public function isSameMoment(Agenda &$a, Agenda &$b)
    {
        $dateDebutA = $a->getDateDebut();
        $dateDebutB = $b->getDateDebut();

        $dateFinA = $a->getDateFin();
        $dateFinB = $b->getDateFin();

        if ($dateDebutA === false || $dateDebutB === false) {
            return false;
        }

        return $this->isSameDate($dateDebutA, $dateDebutB) && $this->isSameDate($dateFinA, $dateFinB);
    }

    public function sanitizeRue($string)
    {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->deleteMultipleSpaces($step2);

        return trim($step3);
    }

    public function sanitizeHTML($string)
    {
        return $this->sanitize(strip_tags($string));
    }

    public function sanitizeVille($string) {
        $string = preg_replace("#-(\s*)st(\s*)-#i", "saint", $string);
        return $this->sanitize($string);
    }

    public function sanitize($string)
    {
        return Monitor::bench('Sanitize', function () use ($string) {
            $key = 'sanitize' . $string;
            if (!$this->cache->contains($key)) {
                $step1 = $this->util->utf8LowerCase($string);
                $step2 = $this->util->replaceAccents($step1);
                $step3 = $this->util->replaceNonAlphanumericChars($step2);
                $step4 = $this->util->deleteStopWords($step3);
                $step5 = $this->util->deleteMultipleSpaces($step4);
                $step6 = trim($step5);
                $this->cache->save($key, $step6);
            }
            return $this->cache->fetch($key);
        });
    }

    private function isSameDate(\DateTime $a, \DateTime $b)
    {
        return $a->format('Y-m-d') === $b->format('Y-m-d');
    }

    private function isSameText($a, $b, $minPourcentage = 100, $nullAreSame = false)
    {
        $trimedA = $this->sanitize($a);
        $trimedB = $this->sanitize($b);

        // = strlen > 0
        if (!isset($trimedA[0]) || !isset($trimedB[0])) {
            return $nullAreSame;
        } elseif ($trimedA === $trimedB) {
            return true;
        } elseif ($minPourcentage < 100) {
            $pourcentage = 0;
            similar_text($a, $b, $pourcentage);
            return $pourcentage >= $minPourcentage;
        }

        return false;
    }
}
