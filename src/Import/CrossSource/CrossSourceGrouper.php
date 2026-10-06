<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

/**
 * Gathers the matched pairs into shows, and checks every two members of a show name it alike.
 */
final readonly class CrossSourceGrouper
{
    public function __construct(private EventTitleComparator $titleComparator)
    {
    }

    /**
     * @param iterable<CrossSourcePair> $pairs the pairs to gather; those the matcher turned down are left out
     *
     * @return list<CrossSourceGroup>
     */
    public function group(iterable $pairs): array
    {
        $parents = [];
        $find = static function (int $id) use (&$parents): int {
            $parents[$id] ??= $id;
            while ($parents[$id] !== $id) {
                $id = $parents[$id] = $parents[$parents[$id]];
            }

            return $id;
        };

        $members = [];
        $contexts = [];
        foreach ($pairs as $pair) {
            if (!$pair->verdict->isMatch()) {
                continue;
            }

            $members[$pair->leftId] = ['source' => $pair->leftSource, 'name' => $pair->leftName];
            $members[$pair->rightId] = ['source' => $pair->rightSource, 'name' => $pair->rightName];
            $parents[$find($pair->leftId)] = $find($pair->rightId);
            // Every pair of a show shares its venue
            $contexts[$pair->leftId] = [$pair->placeName, $pair->cityName];
        }

        $byRoot = [];
        foreach ($members as $id => $member) {
            $byRoot[$find($id)][$id] = $member;
        }

        $groups = [];
        foreach ($byRoot as $root => $groupMembers) {
            ksort($groupMembers);
            $context = $contexts[array_key_first(array_intersect_key($contexts, $groupMembers))] ?? [];
            $groups[$root] = new CrossSourceGroup($groupMembers, $this->conflicts($groupMembers, $context));
        }

        return array_values($groups);
    }

    /**
     * @param array<int, array{source: string, name: string}> $members
     * @param list<string|null>                               $context
     *
     * @return list<array{int, int}>
     */
    private function conflicts(array $members, array $context): array
    {
        $conflicts = [];
        $ids = array_keys($members);
        $count = \count($ids);
        for ($i = 0; $i < $count; ++$i) {
            $left = $ids[$i];
            for ($j = $i + 1; $j < $count; ++$j) {
                $right = $ids[$j];
                if (!$this->titleComparator->compare($members[$left]['name'], $members[$right]['name'], $context)->isMatch()) {
                    $conflicts[] = [$left, $right];
                }
            }
        }

        return $conflicts;
    }
}
