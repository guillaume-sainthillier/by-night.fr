<?php

namespace TBN\AgendaBundle\Geolocalize;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 28/11/2016
 * Time: 21:12.
 */
interface GeolocalizeInterface
{
    public function getLatitude();

    public function getLongitude();
}
