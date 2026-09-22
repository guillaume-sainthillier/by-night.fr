<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\ParserHistory;
use DateTimeImmutable;

final class ParserHistoryHandler
{
    private array $stats = [
        'nbBlacklists' => 0,
        'nbInserts' => 0,
        'nbUpdates' => 0,
        'nbExplorations' => 0,
    ];

    /**
     * Distinct sources (Event::$fromData) of the events of the batch, in first-seen order.
     *
     * @var list<string>
     */
    private array $sources = [];

    private ?ParserHistory $parserHistory = null;

    public function addExploration(): self
    {
        return $this->add('nbExplorations');
    }

    private function add(string $key): self
    {
        if ($this->isStarted()) {
            ++$this->stats[$key];
        }

        return $this;
    }

    public function isStarted(): bool
    {
        return null !== $this->parserHistory;
    }

    public function addUpdate(): self
    {
        return $this->add('nbUpdates');
    }

    public function addInsert(): self
    {
        return $this->add('nbInserts');
    }

    public function addBlackList(): self
    {
        return $this->add('nbBlacklists');
    }

    /**
     * Records where one event of the batch comes from: EventDto::$fromData, the parser's
     * display name that the event keeps as Event::$fromData. A DTO built by hand has none.
     */
    public function addSource(?string $source): self
    {
        if (!$this->isStarted() || null === $source || '' === $source || \in_array($source, $this->sources, true)) {
            return $this;
        }

        $this->sources[] = $source;

        return $this;
    }

    public function stop(): ParserHistory
    {
        $this
            ->parserHistory
            ->setEndDate(new DateTimeImmutable())
            ->setExplorations($this->getNbExplorations() + $this->getNbBlackLists())
            ->setNewEvents($this->getNbInserts())
            ->setUpdatedEvents($this->getNbUpdates())
            ->setFromData($this->sources);

        return $this->parserHistory;
    }

    public function getNbExplorations(): int
    {
        return $this->stats['nbExplorations'];
    }

    public function getNbBlackLists(): int
    {
        return $this->stats['nbBlacklists'];
    }

    public function getNbInserts(): int
    {
        return $this->stats['nbInserts'];
    }

    public function getNbUpdates(): int
    {
        return $this->stats['nbUpdates'];
    }

    public function start(): void
    {
        $this->parserHistory = new ParserHistory()->setStartDate(new DateTimeImmutable());
    }

    public function reset(): void
    {
        // Call GC
        unset($this->parserHistory, $this->stats);

        $this->sources = [];
        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0,
        ];
    }
}
