<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 25/05/2017
 * Time: 14:13.
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
