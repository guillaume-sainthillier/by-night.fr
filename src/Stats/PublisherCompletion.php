<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Entity\Publisher;
use App\Enum\PublisherPageStep;

/**
 * How complete a publisher's page is, shown to its owner with what is left to do.
 */
final readonly class PublisherCompletion
{
    /**
     * @param list<PublisherPageStep> $done
     * @param list<PublisherPageStep> $missing
     */
    private function __construct(
        public array $done,
        public array $missing,
    ) {
    }

    public static function of(Publisher $publisher, PublisherStats $stats): self
    {
        $done = [];
        $missing = [];
        foreach (PublisherPageStep::cases() as $step) {
            if ($step->isDoneBy($publisher, $stats)) {
                $done[] = $step;
            } else {
                $missing[] = $step;
            }
        }

        return new self($done, $missing);
    }

    public function getPercent(): int
    {
        return (int) round(100 * \count($this->done) / \count(PublisherPageStep::cases()));
    }

    public function isComplete(): bool
    {
        return [] === $this->missing;
    }
}
