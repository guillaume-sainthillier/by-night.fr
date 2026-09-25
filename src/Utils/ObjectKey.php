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

/**
 * The keys the import tells its objects apart with, in memory only: a DependencyCatalogue
 * merges the DTOs of a batch sharing one, and an entity provider hands a DTO the entity
 * filed under any of its keys (AbstractEntityProvider::getObjectKeys()). A DTO and its
 * entity find each other only if they spell a key the same way, hence one builder.
 *
 * A key is a prefix, a kind and parts. The prefix ("city", "place") keeps the types of a
 * catalogue apart, the kind keeps a database id, a source's id and a set of fields apart,
 * so "city 12" is never "place 12", nor the city a source numbers 12.
 *
 * The keys of a type whose rule several services share are built here too (timesheet()).
 */
final class ObjectKey
{
    private const string TIMESHEET_PREFIX = 'timesheet';

    /**
     * The database id of an entity, or of the entity a DTO was resolved to.
     */
    public static function internal(string $prefix, int|string $id): string
    {
        return self::join($prefix, 'id', (string) $id);
    }

    /**
     * The id a source gives the object, only unique within that source.
     */
    public static function external(string $prefix, string $origin, string $id): string
    {
        return self::join($prefix, 'external', $origin, $id);
    }

    /**
     * What an object without id is made of (a city's name and postal code). The parts are
     * normalized the way the lookups compare them. Another object's key goes last (see join()).
     */
    public static function data(string $prefix, string ...$parts): string
    {
        return self::join($prefix, 'data', ...$parts);
    }

    /**
     * The object itself, for one nothing identifies: no other live object shares its key.
     */
    public static function transient(string $prefix, object $object): string
    {
        return self::join($prefix, 'spl', (string) spl_object_id($object));
    }

    /**
     * A session of an event, for the import (EventEntityFactory) and the family resolver
     * alike. Sessions are stored as dates, so the time of day a source sends is dropped: two
     * sessions on the same day only differ by their hours label, and an unchanged session
     * keeps its row across imports. A session without end ends the day it starts.
     */
    public static function timesheet(?DateTimeInterface $startAt, ?DateTimeInterface $endAt, ?string $hours): string
    {
        $endAt ??= $startAt;

        return self::data(
            self::TIMESHEET_PREFIX,
            $startAt?->format('Y-m-d') ?? '',
            $endAt?->format('Y-m-d') ?? '',
            $hours ?? '',
        );
    }

    /**
     * Two different lists of segments must never give the same key: the parts come from
     * the feeds, so a place "a-b" on street "c" and a place "a" on street "b-c" are two
     * places, and the catalogue would hand the second one the entity of the first. Text never
     * holds a NUL byte, so no part runs into the next one; a part that is another key holds
     * NULs of its own, which is why it comes last, after a fixed number of parts.
     */
    private static function join(string ...$segments): string
    {
        return implode("\0", $segments);
    }
}
