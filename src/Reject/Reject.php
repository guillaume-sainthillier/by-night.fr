<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Reject;

final class Reject
{
    /**
     * @var int
     */
    public const VALID = 1;

    /**
     * @var int
     */
    public const BAD_EVENT_NAME = 2;

    /**
     * @var int
     */
    public const BAD_EVENT_DATE = 4;

    /**
     * @var int
     */
    public const BAD_EVENT_DATE_INTERVAL = 8;

    /**
     * @var int
     */
    public const SPAM_EVENT_DESCRIPTION = 16;

    /**
     * @var int
     */
    public const BAD_EVENT_DESCRIPTION = 32;

    /**
     * @var int
     */
    public const NO_NEED_TO_UPDATE = 64;

    /**
     * @var int
     */
    public const NO_PLACE_PROVIDED = 128;

    /**
     * @var int
     */
    public const NO_PLACE_LOCATION_PROVIDED = 256;

    /**
     * @var int
     */
    public const BAD_PLACE_NAME = 512;

    /**
     * @var int
     */
    public const BAD_PLACE_LOCATION = 1_024;

    /**
     * @var int
     */
    public const BAD_PLACE_CITY_NAME = 2_048;

    /**
     * @var int
     */
    public const BAD_PLACE_CITY_POSTAL_CODE = 4_096;

    /**
     * @var int
     */
    public const BAD_USER = 8_192;

    /**
     * @var int
     */
    public const EVENT_DELETED = 16_384;

    /**
     * @var int
     */
    public const NO_COUNTRY_PROVIDED = 131_072;

    /**
     * @var int
     */
    public const BAD_COUNTRY = 262_144;

    protected int $reason = self::VALID;

    public function getReason(): int
    {
        return $this->reason;
    }

    public function setReason(int $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function removeReason(int $reason): self
    {
        $this->reason &= ~$reason;

        return $this;
    }

    public function setValid(): self
    {
        $this->reason = self::VALID;

        return $this;
    }

    public function addReason(int $reason): self
    {
        $this->reason |= $reason;

        return $this;
    }

    public function isValid(): bool
    {
        return self::VALID === $this->reason;
    }

    public function isEventDeleted(): bool
    {
        return $this->hasReason(self::EVENT_DELETED);
    }

    private function hasReason(int $reason): bool
    {
        return $reason === ($reason & $this->reason);
    }

    public function isBadUser(): bool
    {
        return $this->hasReason(self::BAD_USER);
    }

    public function hasNoPlaceLocationProvided(): bool
    {
        return $this->hasReason(self::NO_PLACE_LOCATION_PROVIDED);
    }

    public function hasNoPlaceProvided(): bool
    {
        return $this->hasReason(self::NO_PLACE_PROVIDED);
    }

    public function isBadPlaceCityPostalCode(): bool
    {
        return $this->hasReason(self::BAD_PLACE_CITY_POSTAL_CODE);
    }

    public function isBadPlaceCityName(): bool
    {
        return $this->hasReason(self::BAD_PLACE_CITY_NAME);
    }

    public function isBadPlaceLocation(): bool
    {
        return $this->hasReason(self::BAD_PLACE_LOCATION);
    }

    public function isBadPlaceName(): bool
    {
        return $this->hasReason(self::BAD_PLACE_NAME);
    }

    public function hasNoNeedToUpdate(): bool
    {
        return $this->hasReason(self::NO_NEED_TO_UPDATE);
    }

    public function isBadEventDescription(): bool
    {
        return $this->hasReason(self::BAD_EVENT_DESCRIPTION);
    }

    public function isBadEventName(): bool
    {
        return $this->hasReason(self::BAD_EVENT_NAME);
    }

    public function isBadEventDate(): bool
    {
        return $this->hasReason(self::BAD_EVENT_DATE);
    }

    public function isBadEventDateInterval(): bool
    {
        return $this->hasReason(self::BAD_EVENT_DATE_INTERVAL);
    }

    public function isSpamEventDescription(): bool
    {
        return $this->hasReason(self::SPAM_EVENT_DESCRIPTION);
    }

    public function hasNoCountryProvided(): bool
    {
        return $this->hasReason(self::NO_COUNTRY_PROVIDED);
    }

    public function isBadCountryName(): bool
    {
        return $this->hasReason(self::BAD_COUNTRY);
    }
}
