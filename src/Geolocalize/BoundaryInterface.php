<?php

namespace App\Geolocalize;

interface BoundaryInterface extends GeolocalizeInterface
{
    /**
     * @return float
     */
    public function getDistanceMax();
}
