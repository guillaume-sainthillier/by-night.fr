<?php

namespace App\Reject;

use LogicException;

class Reject
{
    const VALID = 1;

    const BAD_EVENT_NAME = 2;

    const BAD_EVENT_DATE = 4;

    const BAD_EVENT_DATE_INTERVAL = 8;

    const SPAM_EVENT_DESCRIPTION = 16;

    const BAD_EVENT_DESCRIPTION = 32;

    const NO_NEED_TO_UPDATE = 64;

    const NO_PLACE_PROVIDED = 128;

    const NO_PLACE_LOCATION_PROVIDED = 256;

    const BAD_PLACE_NAME = 512;

    const BAD_PLACE_LOCATION = 1024;

    const BAD_PLACE_CITY_NAME = 2048;

    const BAD_PLACE_CITY_POSTAL_CODE = 4096;

    const BAD_USER = 8192;

    const EVENT_DELETED = 16384;

    const AMBIGOUS_ZIP = 32768;

    const AMBIGOUS_CITY = 65536;

    const NO_COUNTRY_PROVIDED = 131072;

    const BAD_COUNTRY = 262144;

    const GEOCODE_LIMIT = 524288;

    protected $reason;

    public function __construct()
    {
        $this->reason = self::VALID;
    }

    public function getReason()
    {
        return $this->reason;
    }

    public function setReason($reason)
    {
        if (null === $reason) {
            throw new LogicException('Reason must be integer');
        }
        $this->reason = $reason;

        return $this;
    }

    public function removeReason($reason)
    {
        $this->reason &= ~$reason;

        return $this;
    }

    public function setValid()
    {
        $this->reason = self::VALID;

        return $this;
    }

    public function addReason($reason)
    {
        $this->reason |= $reason;

        return $this;
    }

    public function isValid()
    {
        return self::VALID === $this->reason;
    }

    public function isEventDeleted()
    {
        return $this->hasReason(self::EVENT_DELETED);
    }

    private function hasReason(int $reason)
    {
        return $reason === ($reason & $this->reason);
    }

    public function isBadUser()
    {
        return $this->hasReason(self::BAD_USER);
    }

    public function hasNoPlaceLocationProvided()
    {
        return $this->hasReason(self::NO_PLACE_LOCATION_PROVIDED);
    }

    public function hasNoPlaceProvided()
    {
        return $this->hasReason(self::NO_PLACE_PROVIDED);
    }

    public function isBadPlaceCityPostalCode()
    {
        return $this->hasReason(self::BAD_PLACE_CITY_POSTAL_CODE);
    }

    public function isBadPlaceCityName()
    {
        return $this->hasReason(self::BAD_PLACE_CITY_NAME);
    }

    public function isBadPlaceLocation()
    {
        return $this->hasReason(self::BAD_PLACE_LOCATION);
    }

    public function isBadPlaceName()
    {
        return $this->hasReason(self::BAD_PLACE_NAME);
    }

    public function hasNoNeedToUpdate()
    {
        return $this->hasReason(self::NO_NEED_TO_UPDATE);
    }

    public function isBadEventDescription()
    {
        return $this->hasReason(self::BAD_EVENT_DESCRIPTION);
    }

    public function isBadEventName()
    {
        return $this->hasReason(self::BAD_EVENT_NAME);
    }

    public function isBadEventDate()
    {
        return $this->hasReason(self::BAD_EVENT_DATE);
    }

    public function isBadEventDateInterval()
    {
        return $this->hasReason(self::BAD_EVENT_DATE_INTERVAL);
    }

    public function isSpamEventDescription()
    {
        return $this->hasReason(self::SPAM_EVENT_DESCRIPTION);
    }

    public function hasNoCountryProvided()
    {
        return $this->hasReason(self::NO_COUNTRY_PROVIDED);
    }

    public function isBadCountryName()
    {
        return $this->hasReason(self::BAD_COUNTRY);
    }
}
