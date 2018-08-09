<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 25/05/2017
 * Time: 14:13.
 */

namespace App\Geolocalize;

class Boundary implements BoundaryInterface
{
    private $lat;

    private $lng;

    private $distanceMax;

    public function __construct($lat, $lng, $distanceMax = 5.0)
    {
        $this->lat         = $lat;
        $this->lng         = $lng;
        $this->distanceMax = $distanceMax;
    }

    public function getLatitude()
    {
        return $this->lat;
    }

    public function getLongitude()
    {
        return $this->lng;
    }

    public function getDistanceMax()
    {
        return $this->distanceMax;
    }
}
