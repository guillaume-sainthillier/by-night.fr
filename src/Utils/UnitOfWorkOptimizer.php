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
    public static function getDateTimeValue(
        ?DateTimeInterface $originalValue,
        ?DateTimeInterface $newValue,
    ): ?DateTimeInterface {
        return self::getDateTimeInterfaceValue($originalValue, $newValue, 'Y-m-d H:i:s');
    }

    /**
     * The original array when the new one would be stored the same, so Doctrine (which compares arrays with ===) sees
     * no change. "simple_array" stores [] as NULL and loads NULL as [], so a merge setting null back on a row loaded
     * with [] was a change on every flush (the dimensions of an imported event's image).
     *
     * Otherwise as strict as Doctrine: same keys, same order, same types. A "simple_array" loads its values as
     * strings, so its caller casts the new ones the same way.
     *
     * @template T of array
     *
     * @param T|null $originalValue
     * @param T|null $newValue
     *
     * @return T|null
     */
    public static function getArrayValue(?array $originalValue, ?array $newValue): ?array
    {
        if (($originalValue ?? []) === ($newValue ?? [])) {
            return $originalValue;
        }

        return $newValue;
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
