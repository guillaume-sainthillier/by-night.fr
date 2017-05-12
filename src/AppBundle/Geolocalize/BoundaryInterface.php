<?php

namespace AppBundle\Geolocalize;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 28/11/2016
 * Time: 21:12
 */
interface BoundaryInterface extends GeolocalizeInterface
{
    /**
     * @return float
     */
    public function getDistanceMax();
}
