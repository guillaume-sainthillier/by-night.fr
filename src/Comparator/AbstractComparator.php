<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\ComparatorInterface;
use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\ExternalIdentifiablesInterface;
use App\Contracts\MatchingInterface;
use App\Utils\StringManipulator;

abstract class AbstractComparator implements ComparatorInterface
{
    public function getMostMatching(iterable $entities, object $dto): ?MatchingInterface
    {
        \assert($dto instanceof ExternalIdentifiableInterface);

        //Search by exact matching only
        if (null !== $dto->getExternalId() && null !== $dto->getExternalOrigin()) {
            foreach ($entities as $entity) {
                if ($entity instanceof ExternalIdentifiablesInterface) {
                    $externals = $entity->getExternalIdentifiables();
                } elseif ($entity instanceof ExternalIdentifiableInterface) {
                    $externals = [$entity];
                } else {
                    throw new \LogicException(sprintf('Unable to fetch ids from "%s" class', \get_class($entity)));
                }

                foreach ($externals as $external) {
                    if ($external->getExternalId() === $dto->getExternalId()) {
                        return new Matching($entity, 100.0);
                    }
                }
            }
        }

        $maxConfidence = -1;
        $result = null;
        foreach ($entities as $entity) {
            $matching = $this->getMatching($entity, $dto);

            if (null !== $matching && $maxConfidence < $matching->getConfidence()) {
                $maxConfidence = $matching->getConfidence();
                $result = $matching;

                if ($maxConfidence >= 100.0) {
                    return $result;
                }
            }
        }

        return $result;
    }

    protected function getStringMatchingConfidence(?string $leftText, ?string $rightText): float
    {
        if (null === $leftText || null === $rightText) {
            return 0.0;
        }

        // = strlen > 0
        if (isset($leftText[0]) && isset($rightText[0]) > 0) {
            if (mb_strlen($leftText) > 255 || mb_strlen($rightText) > 255) {
                similar_text($leftText, $rightText, $confidence);

                return $confidence;
            }

            return $this->getLevenshteinDistance($leftText, $rightText);
        }

        return 0.0;
    }

    private function getLevenshteinDistance($a, $b): float
    {
        return (float) ((1 - levenshtein($a, $b) / max(mb_strlen($a), mb_strlen($b))) * 100);
    }

    protected function sanitize(string $content): string
    {
        $manipulator = new StringManipulator($content);

        return $manipulator
            ->deleteStopWords()
            ->replaceAccents()
            ->nonAlphanumericChars()
            ->lowerCase()
            ->deleteMultipleSpaces()
            ->toString();
    }
}
