<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Comparator;

use App\Contracts\MatchingInterface;

class Matching implements MatchingInterface
{
    private ?object $entity;
    private float $confidence;

    public function __construct(?object $entity = null, float $confidence = 0.0)
    {
        $this->entity = $entity;
        $this->confidence = $confidence;
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
