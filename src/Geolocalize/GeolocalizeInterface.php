<?php

namespace App\Geolocalize;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 28/11/2016
 * Time: 21:12.
 */
interface GeolocalizeInterface
{
    /**
     * @return float
     */
    public function getLatitude();

    /**
     * @return float
     */
    public function getLongitude();
}
