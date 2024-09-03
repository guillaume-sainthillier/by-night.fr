<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;

final readonly class Matching implements MatchingInterface
{
    public function __construct(private ?object $entity = null, private float $confidence = 0.0)
    {
    }

    public function getEntity(): ?object
    {
        return $this->entity;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }
}
