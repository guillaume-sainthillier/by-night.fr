<?php

namespace TBN\MajDataBundle\Reject;

/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/11/2016
 * Time: 23:01.
 */
class Reject
{
    const VALID                      = 1;

    const BAD_EVENT_NAME             = 2;

    const BAD_EVENT_DATE             = 4;

    const BAD_EVENT_DATE_INTERVAL    = 8;

    const SPAM_EVENT_DESCRIPTION     = 16;

    const BAD_EVENT_DESCRIPTION      = 32;

    const NO_NEED_TO_UPDATE          = 64;

    const NO_PLACE_PROVIDED          = 128;

    const NO_PLACE_LOCATION_PROVIDED = 256;

    const BAD_PLACE_NAME             = 512;

    const BAD_PLACE_LOCATION         = 1024;

    const BAD_PLACE_CITY_NAME        = 2048;

    const BAD_PLACE_CITY_POSTAL_CODE = 4096;

    const BAD_USER                   = 8192;

    const EVENT_DELETED              = 16384;

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
            throw new \LogicException('Reason must be integer');
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
        return self::EVENT_DELETED === (self::EVENT_DELETED & $this->reason);
    }

    public function isBadUser()
    {
        return self::BAD_USER === (self::BAD_USER & $this->reason);
    }

    public function hasNoPlaceLocationProvided()
    {
        return self::NO_PLACE_LOCATION_PROVIDED === (self::NO_PLACE_LOCATION_PROVIDED & $this->reason);
    }

    public function hasNoPlaceProvided()
    {
        return self::NO_PLACE_PROVIDED === (self::NO_PLACE_PROVIDED & $this->reason);
    }

    public function isBadPlaceCityPostalCode()
    {
        return self::BAD_PLACE_CITY_POSTAL_CODE === (self::BAD_PLACE_CITY_POSTAL_CODE & $this->reason);
    }

    public function isBadPlaceCityName()
    {
        return self::BAD_PLACE_CITY_NAME === (self::BAD_PLACE_CITY_NAME & $this->reason);
    }

    public function isBadPlaceLocation()
    {
        return self::BAD_PLACE_LOCATION === (self::BAD_PLACE_LOCATION & $this->reason);
    }

    public function isBadPlaceName()
    {
        return self::BAD_PLACE_NAME === (self::BAD_PLACE_NAME & $this->reason);
    }

    public function hasNoNeedToUpdate()
    {
        return self::NO_NEED_TO_UPDATE === (self::NO_NEED_TO_UPDATE & $this->reason);
    }

    public function isBadEventDescription()
    {
        return self::BAD_EVENT_DESCRIPTION === (self::BAD_EVENT_DESCRIPTION & $this->reason);
    }

    public function isBadEventName()
    {
        return self::BAD_EVENT_NAME === (self::BAD_EVENT_NAME & $this->reason);
    }

    public function isBadEventDate()
    {
        return self::BAD_EVENT_DATE === (self::BAD_EVENT_DATE & $this->reason);
    }

    public function isBadEventDateInterval()
    {
        return self::BAD_EVENT_DATE_INTERVAL === (self::BAD_EVENT_DATE_INTERVAL & $this->reason);
    }

    public function isSpamEventDescription()
    {
        return self::SPAM_EVENT_DESCRIPTION === (self::SPAM_EVENT_DESCRIPTION & $this->reason);
    }
}
