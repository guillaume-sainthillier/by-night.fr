<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\City;
use App\Entity\Place;
use App\Entity\ZipCity;
use Exception;

final readonly class Comparator
{
    public function __construct(private Util $util)
    {
    }

    public function getBestPlace(array $places, ?Place $testedPlace = null, int $minScore = 90): ?Place
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

    public function isExactSamePlace(?Place $a = null, ?Place $b = null): bool
    {
        if (null === $a || null === $b) {
            return false;
        }

        return ($a->getExternalId() && $a->getExternalId() === $b->getExternalId())
            || ($a->getId() && $a->getId() === $b->getId());
    }

    public function getMatchingScorePlace(?Place $a = null, ?Place $b = null): int
    {
        if (null === $a || null === $b) {
            return 0;
        }

        if ($this->isExactSamePlace($a, $b)) {
            return 100;
        }

        if (($a->getCity() && $b->getCity() && $a->getCity()->getId() === $b->getCity()->getId())
            || ($a->getZipCity() && $b->getZipCity() && $a->getZipCity()->getId() === $b->getZipCity()->getId())
            || (!$a->getCity() && !$b->getCity() && !$a->getZipCity() && !$b->getZipCity() && $a->getCountry() && $b->getCountry() && $a->getCountry()->getId() === $b->getCountry()->getId())) {
            $matchingScoreNom = $this->getMatchingScoreTextWithoutCity(
                $a->getName(), $a->getCity(), $a->getZipCity(),
                $b->getName(), $b->getCity(), $b->getZipCity()
            );

            // Même rue & ~ même nom
            if ($matchingScoreNom >= 80
                && $this->getMatchingScoreRue($a->getStreet(), $b->getStreet()) >= 90
            ) {
                return 100;
            }

            // ~ Même nom
            if ($matchingScoreNom >= 80) {
                return 90;
            }
        }

        return 0;
    }

    private function getMatchingScoreTextWithoutCity(?string $a, ?City $cityA = null, ?ZipCity $zipCityA = null, ?string $b = null, ?City $cityB = null, ?ZipCity $zipCityB = null): int|float
    {
        if ($a && $a === $b) {
            return 100;
        }

        if (null !== $cityA) {
            $a = str_ireplace((string) $cityA->getName(), '', (string) $a);
        } elseif (null !== $zipCityA) {
            $a = str_ireplace((string) $zipCityA->getName(), '', (string) $a);
        }

        if (null !== $cityB) {
            $b = str_ireplace((string) $cityB->getName(), '', (string) $b);
        } elseif (null !== $zipCityB) {
            $b = str_ireplace((string) $zipCityB->getName(), '', (string) $b);
        }

        $a = $this->sanitize($a);
        $b = $this->sanitize($b);

        return $this->getMatchingScore($a, $b);
    }

    public function sanitize(?string $string): string
    {
        if (null === $string || '' === trim($string)) {
            return '';
        }

        return Monitor::bench('Sanitize', function () use ($string) {
            $string = $this->util->deleteStopWords($string);
            $string = $this->util->utf8LowerCase($string);
            $string = $this->util->replaceAccents($string);
            $string = $this->util->replaceNonAlphanumericChars($string);
            $string = $this->util->deleteStopWords($string);
            $string = $this->util->deleteMultipleSpaces($string);

            return trim((string) $string);
        });
    }

    private function getMatchingScore(string $a, string $b): float|int
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
                } catch (Exception) {
                }
            }
        }

        return $pourcentage;
    }

    private function getDiffPourcentage(string $a, string $b): int
    {
        return (int) ((1 - levenshtein($a, $b) / max(mb_strlen($a), mb_strlen($b))) * 100);
    }

    private function getMatchingScoreRue(?string $a, ?string $b): int|float
    {
        if ($a && $a === $b) {
            return 100;
        }

        $trimedA = $this->sanitizeRue($a);
        $trimedB = $this->sanitizeRue($b);

        return $this->getMatchingScore($trimedA, $trimedB);
    }

    public function sanitizeRue(?string $string): string
    {
        if (null === $string) {
            return '';
        }

        $step1 = $this->util->utf8LowerCase($string);
        $step2 = $this->util->replaceAccents($step1);
        $step3 = $this->util->deleteMultipleSpaces($step2);

        return trim((string) $step3);
    }

    public function sanitizeNumber(?string $string): ?string
    {
        return preg_replace('#\D#', '', (string) $string);
    }

    public function sanitizeVille(?string $string): string
    {
        $string = preg_replace("#(^|[\s-]+)st([\s-]+)#i", 'saint', (string) $string);
        $string = str_replace(' ', '', (string) $string);

        return $this->sanitize($string);
    }
}
