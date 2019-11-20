<?php

namespace App\Geolocalize;

interface GeolocalizeInterface
{
    public function getLatitude(): ?float;

    public function getLongitude(): ?float;
}
