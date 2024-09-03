<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use App\Entity\Event;
use App\Entity\Place;

final readonly class Merger
{
    /**
     * @var string
     */
    public const MERGE_LEFT = 'do_merge_left';

    /**
     * @var string
     */
    public const MERGE_RIGHT = 'do_merge_right';

    /**
     * @var string
     */
    public const MERGE_MAX = 'do_merge_max';

    /**
     * @var string
     */
    public const MERGE_RIGHT_IF_DIFFERENT = 'do_merge_right_if_different';

    /**
     * @var string
     */
    public const MERGE_RIGHT_IF_DATE_DIFFERENT = 'do_merge_right_if_date_different';

    /**
     * @var string
     */
    public const FORCE_MERGE_LEFT = 'do_force_merge_left';

    /**
     * @var string
     */
    public const FORCE_MERGE_RIGHT = 'do_force_merge_right';

    /**
     * @var string
     */
    public const DEFAULT_MERGE = self::MERGE_RIGHT;

    public function __construct(private Comparator $comparator)
    {
    }

    public function mergeEvent(?Event $a = null, ?Event $b = null): ?object
    {
        return $this->merge($a, $b, [
            'name',
            'start_date' => self::MERGE_RIGHT_IF_DATE_DIFFERENT,
            'end_date' => self::MERGE_RIGHT_IF_DATE_DIFFERENT,
            'description',
            'hours',
            'status',
            'type',
            'category',
            'theme',
            'phoneContacts',
            'mailContacts',
            'websiteContacts',
            'prices',
            'url',
            'facebook_event_id',
            'facebook_owner_id',
            'source',
            'external_updated_at' => self::MERGE_RIGHT_IF_DIFFERENT,
            'from_data',
            'parser_version',
            'reject',
            'placeReject',
        ]);
    }

    /**
     * Merge les champs de b dans a s'ils sont jugés plus pertinents.
     */
    private function merge(?object $a, ?object $b, array $fields = []): ?object
    {
        // Un ou les deux est nul, pas la peine de merger
        if (null === $a || null === $b) {
            return $a ?: $b; // Retourne l'objet non nul s'il existe
        }

        if ($a === $b) {
            return $a;
        }

        foreach ($fields as $type => $field) {
            if (is_numeric($type)) {
                $type = self::DEFAULT_MERGE;
            } else {
                $oldField = $field;
                $field = $type;
                $type = $oldField;
            }

            $getter = 'get' . $this->skakeToCamel($field);
            $setter = 'set' . $this->skakeToCamel($field);

            $valueA = $a->$getter();
            $valueB = $b->$getter();
            $value = $this->getBestContent($valueA, $valueB, $type);

            $a->$setter($value);
        }

        return $a;
    }

    private function skakeToCamel(?string $str): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $str)));
    }

    private function getBestContent(mixed $valueA, mixed $valueB, string $mergeType): mixed
    {
        if (\is_callable($mergeType)) {
            return \call_user_func($mergeType, $valueA, $valueB);
        }

        switch ($mergeType) {
            case self::MERGE_RIGHT:
                return $valueB ?: $valueA;
            case self::MERGE_LEFT:
                return $valueA ?: $valueB;
            case self::FORCE_MERGE_LEFT:
                return $valueA;
            case self::FORCE_MERGE_RIGHT:
                return $valueB;
            case self::MERGE_RIGHT_IF_DIFFERENT:
                return $valueA != $valueB ? $valueB : $valueA;
            case self::MERGE_RIGHT_IF_DATE_DIFFERENT:
                if ($valueA && $valueB) {
                    return $valueA->format('Y-m-d') !== $valueB->format('Y-m-d') ? $valueB : $valueA;
                }

                return $this->getBestContent($valueA, $valueB, self::MERGE_RIGHT_IF_DIFFERENT);
            case self::MERGE_MAX:
                return max($valueA, $valueB);
        }

        if (\is_bool($valueA)) {
            return $valueA;
        }

        if (\is_object($valueA) || \is_object($valueB)) {
            return $valueA ?: $valueB;
        }

        $compareA = $this->comparator->sanitize($valueA);

        return isset($compareA[0]) ? ($valueA ?: null) : ($valueB ?: null);
    }

    public function mergePlace(?Place $a = null, ?Place $b = null): ?object
    {
        return $this->merge($a, $b, [
            'name' => self::MERGE_LEFT,
            'latitude' => self::MERGE_LEFT,
            'longitude' => self::MERGE_LEFT,
            'street' => self::MERGE_LEFT,
            'url' => self::MERGE_LEFT,
            'cityName' => self::MERGE_LEFT,
            'codePostalCode' => self::MERGE_LEFT,
            'facebook_id' => self::MERGE_LEFT,
            'external_id' => self::MERGE_LEFT,
            'reject',
        ]);
    }
}
