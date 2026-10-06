<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Entity\Event;

/**
 * The families EventFamilyResolver gathers: the events joined, two by two, by what proves they are the same event (a
 * shared identity hash, a cross-source link), and every event joined to one of them.
 */
final class EventFamilies
{
    /** @var array<int, Event> */
    private array $events = [];

    /** @var array<int, int> */
    private array $parents = [];

    /**
     * @return bool whether the event was not known yet
     */
    public function add(Event $event): bool
    {
        $id = (int) $event->getId();
        if (isset($this->events[$id])) {
            return false;
        }

        $this->events[$id] = $event;
        $this->parents[$id] = $id;

        return true;
    }

    public function has(int $id): bool
    {
        return isset($this->events[$id]);
    }

    public function join(int $left, int $right): void
    {
        if (!$this->has($left) || !$this->has($right)) {
            return;
        }

        $this->parents[$this->root($left)] = $this->root($right);
    }

    /**
     * Whether both belong to the same family. An event never gathered belongs to none.
     */
    public function together(Event $left, Event $right): bool
    {
        $leftId = (int) $left->getId();
        $rightId = (int) $right->getId();

        return $this->has($leftId) && $this->has($rightId) && $this->root($leftId) === $this->root($rightId);
    }

    /**
     * The families of two events or more, their members in id order.
     *
     * @return list<non-empty-list<Event>>
     */
    public function families(): array
    {
        $families = [];
        foreach ($this->events as $id => $event) {
            $families[$this->root($id)][$id] = $event;
        }

        $result = [];
        foreach ($families as $members) {
            if (\count($members) > 1) {
                ksort($members);
                $result[] = array_values($members);
            }
        }

        return $result;
    }

    private function root(int $id): int
    {
        while ($this->parents[$id] !== $id) {
            $id = $this->parents[$id] = $this->parents[$this->parents[$id]];
        }

        return $id;
    }
}
