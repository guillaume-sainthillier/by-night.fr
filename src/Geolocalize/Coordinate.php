<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 25/05/2017
 * Time: 14:44.
 */

namespace App\Geolocalize;

use Geocoder\Assert;

class Coordinate implements GeolocalizeInterface
{
    /**
     * @var float
     */
    private $latitude;

    /**
     * @var float
     */
    private $longitude;

    /**
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct($latitude, $longitude)
    {
        Assert::notNull($latitude);
        Assert::notNull($longitude);

        $latitude  = (float) $latitude;
        $longitude = (float) $longitude;

        Assert::latitude($latitude);
        Assert::longitude($longitude);

        $this->latitude  = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Returns the latitude.
     *
     * @return float
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * Returns the longitude.
     *
     * @return float
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }
}
