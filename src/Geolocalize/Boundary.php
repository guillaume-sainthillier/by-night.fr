<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Geolocalize;

class Boundary extends Coordinate implements BoundaryInterface
{
    private $distanceMax;

    public function __construct(float $lat, float $lng, float $distanceMax = 5.0)
    {
        parent::__construct($lat, $lng);

        $this->distanceMax = $distanceMax;
    }

    public function getDistanceMax()
    {
        return $this->distanceMax;
    }
}
