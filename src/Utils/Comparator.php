<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\City;
use App\Entity\Place;
use App\Entity\ZipCity;
use Exception;

class Comparator
{
    private Util $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    /**
     * @return Place|null
     */
    public function getBestPlace(array $places, Place $testedPlace = null, $minScore = 90)
    {
        if (null === $testedPlace) {
            return null;
        }

        foreach ($places as $place) {
            if ($this->isExactSamePlace($place, $testedPlace)) {
                return $place;
            }
        }

        $bestScore = 0;
        $bestItem = null;
        foreach ($places as $place) {
            $score = $this->getMatchingScorePlace($place, $testedPlace);

            if ($score >= 100) {
                return $place;
            } elseif ($score >= $minScore && $score > $bestScore) {
                $bestItem = $place;
                $bestScore = $score;
            }
        }

        return $bestItem;
    }

    public function isExactSamePlace(Place $a = null, Place $b = null)
    {
        if (null === $a || null === $b) {
            return false;
        }

        return ($a->getExternalId() && $a->getExternalId() === $b->getExternalId()) ||
            ($a->getId() && $a->getId() === $b->getId());
    }

    public function getMatchingScorePlace(Place $a = null, Place $b = null)
    {
        if (null === $a || null === $b) {
            return 0;
        }

        if ($this->isExactSamePlace($a, $b)) {
            return 100;
        }

        if (($a->getCity() && $b->getCity() && $a->getCity()->getId() === $b->getCity()->getId()) ||
            ($a->getZipCity() && $b->getZipCity() && $a->getZipCity()->getId() === $b->getZipCity()->getId()) ||
            (!$a->getCity() && !$b->getCity() && !$a->getZipCity() && !$b->getZipCity() && $a->getCountry() && $b->getCountry() && $a->getCountry()->getId() === $b->getCountry()->getId())) {
            $matchingScoreNom = $this->getMatchingScoreTextWithoutCity(
                $a->getNom(), $a->getCity(), $a->getZipCity(),
                $b->getNom(), $b->getCity(), $b->getZipCity()
            );

            //Même rue & ~ même nom
            if ($matchingScoreNom >= 80 &&
                $this->getMatchingScoreRue($a->getRue(), $b->getRue()) >= 90
            ) {
                return 100;
            }

            //~ Même nom
            if ($matchingScoreNom >= 80) {
                return 90;
            }
        }

        return 0;
    }

    private function getMatchingScoreTextWithoutCity($a, City $cityA = null, ZipCity $zipCityA = null, $b = null, City $cityB = null, ZipCity $zipCityB = null)
    {
        if ($a && $a === $b) {
            return 100;
        }

        if (null !== $cityA) {
            $a = \str_ireplace($cityA->getName(), '', $a);
        } elseif (null !== $zipCityA) {
            $a = \str_ireplace($zipCityA->getName(), '', $a);
        }

        if (null !== $cityB) {
            $b = \str_ireplace($cityB->getName(), '', $b);
        } elseif (null !== $zipCityB) {
            $b = \str_ireplace($zipCityB->getName(), '', $b);
        }

        $a = $this->sanitize($a);
        $b = $this->sanitize($b);

        return $this->getMatchingScore($a, $b);
    }

    public function sanitize($string)
    {
        return Monitor::bench('Sanitize', function () use ($string) {
            $string = $this->util->deleteStopWords($string);
            $string = $this->util->utf8LowerCase($string);
            $string = $this->util->replaceAccents($string);
            $string = $this->util->replaceNonAlphanumericChars($string);
            $string = $this->util->deleteStopWords($string);
            $string = $this->util->deleteMultipleSpaces($string);

            return \trim($string);
        });
    }

    private function getMatchingScore($a, $b)
    {
        $pourcentage = 0;
        // = strlen > 0
        if (isset($a[0]) && isset($b[0]) > 0) {
            if ($a === $b) {
                return 100;
            }

            if (isset($a[250]) || isset($b[250])) {
                \similar_text($a, $b, $pourcentage);
            } else {
                try {
                    $pourcentage = $this->getDiffPourcentage($a, $b);
                } catch (Exception $ex) {
                }
            }
        }

        return $pourcentage;
    }

    private function getDiffPourcentage($a, $b)
    {
        return (1 - \levenshtein($a, $b) / \max(\mb_strlen($a), \mb_strlen($b))) * 100;
    }

    private function getMatchingScoreRue($a, $b)
    {
        if ($a && $a === $b) {
            return 100;
        }

        $trimedA = $this->sanitizeRue($a);
        $trimedB = $this->sanitizeRue($b);

        return $this->getMatchingScore($trimedA, $trimedB);
    }

    public function sanitizeRue($string)
    {
        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->deleteMultipleSpaces($step2);

        return \trim($step3);
    }

    public function sanitizeNumber($string)
    {
        return \preg_replace('/\D/', '', $string);
    }

    public function sanitizeVille($string)
    {
        $string = \preg_replace("#(^|[\s-]+)st([\s-]+)#i", 'saint', $string);
        $string = str_replace(' ', '', $string);

        return $this->sanitize($string);
    }
}
