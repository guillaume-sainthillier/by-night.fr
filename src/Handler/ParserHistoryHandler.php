<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\ParserHistory;
use DateTime;

class ParserHistoryHandler
{
    private array $stats;

    private ?ParserHistory $parserHistory = null;

    public function __construct()
    {
        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0,
        ];
    }

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

    public function stop(): ParserHistory
    {
        $this
            ->parserHistory
            ->setEndDate(new DateTime())
            ->setExplorations($this->getNbExplorations() + $this->getNbBlackLists())
            ->setNouvellesSoirees($this->getNbInserts())
            ->setUpdateSoirees($this->getNbUpdates())
            ->setFromData('?');

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
        $this->parserHistory = (new ParserHistory())->setStartDate(new DateTime());
    }

    public function reset(): void
    {
        // Call GC
        unset($this->parserHistory, $this->stats);

        $this->stats = [
            'nbBlacklists' => 0,
            'nbInserts' => 0,
            'nbUpdates' => 0,
            'nbExplorations' => 0,
        ];
    }
}
