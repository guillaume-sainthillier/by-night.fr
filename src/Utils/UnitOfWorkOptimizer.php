<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use DateTimeInterface;

final class UnitOfWorkOptimizer
{
    /**
     * @template T of DateTimeInterface
     *
     * @param T|null $originalValue
     * @param T|null $newValue
     *
     * @return T|null
     */
    public static function getDateValue(
        ?DateTimeInterface $originalValue,
        ?DateTimeInterface $newValue,
    ): ?DateTimeInterface {
        return self::getDateTimeInterfaceValue($originalValue, $newValue, 'Y-m-d');
    }

    /**
     * @template T of DateTimeInterface
     *
     * @param T|null $originalValue
     * @param T|null $newValue
     *
     * @return T|null
     */
    public static function getDateTimeValue(
        ?DateTimeInterface $originalValue,
        ?DateTimeInterface $newValue,
    ): ?DateTimeInterface {
        return self::getDateTimeInterfaceValue($originalValue, $newValue, 'Y-m-d H:i:s');
    }

    /**
     * @template T of DateTimeInterface
     *
     * @param T|null $originalValue
     * @param T|null $newValue
     *
     * @return T|null
     */
    public static function getTimeValue(
        ?DateTimeInterface $originalValue,
        ?DateTimeInterface $newValue,
    ): ?DateTimeInterface {
        return self::getDateTimeInterfaceValue($originalValue, $newValue, 'H:i:s');
    }

    /**
     * @template T of DateTimeInterface
     *
     * @param T|null $originalValue
     * @param T|null $newValue
     *
     * @return T|null
     */
    private static function getDateTimeInterfaceValue(
        ?DateTimeInterface $originalValue,
        ?DateTimeInterface $newValue,
        string $format,
    ): ?DateTimeInterface {
        if (null === $originalValue || null === $newValue) {
            return $newValue;
        }

        if ($originalValue->format($format) === $newValue->format($format)) {
            return $originalValue;
        }

        return $newValue;
    }
}
