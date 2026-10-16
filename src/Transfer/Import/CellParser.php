<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Transfer\Sheet\EventColumn;
use DateTimeImmutable;

/**
 * Reads the cells of a member's spreadsheet as the event's values, the way people write them in French.
 */
final class CellParser
{
    private const array DATE_FORMATS = ['d/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd.m.Y'];

    /**
     * A date ("12/03/2027", "12/03/27", "2027-03-12"), null for an empty cell.
     */
    public static function date(string $value, EventColumn $column): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if (false !== $date && $date->format($format) === $value) {
                return $date;
            }
        }

        throw new InvalidCellException($column, \sprintf('« %s » n’est pas une date (JJ/MM/AAAA).', $value));
    }

    /**
     * A time of day ("20:30", "20h30", "20h"), on 1 January 1970 like the form's, null for an empty cell.
     */
    public static function time(string $value, EventColumn $column): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        if (1 !== preg_match('/^(\d{1,2})\s*[:hH.]\s*(\d{2})?$/', $value, $matches) || (int) $matches[1] > 23 || (int) ($matches[2] ?? 0) > 59) {
            throw new InvalidCellException($column, \sprintf('« %s » n’est pas une heure (HH:MM).', $value));
        }

        return new DateTimeImmutable(\sprintf('1970-01-01 %02d:%02d', $matches[1], $matches[2] ?? 0));
    }

    /**
     * Yes or no ("oui", "non", "x"…), null for an empty cell.
     */
    public static function bool(string $value, EventColumn $column): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            '' => null,
            'oui', 'o', 'yes', 'y', 'x', '1', 'vrai', 'true' => true,
            'non', 'n', 'no', '0', 'faux', 'false' => false,
            default => throw new InvalidCellException($column, \sprintf('« %s » : écrivez « oui » ou « non ».', $value)),
        };
    }

    /**
     * The items of a list cell ("Jazz, Blues"), without the empty ones.
     *
     * @return list<string>
     */
    public static function list(string $value): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split(EventColumn::LIST_SEPARATOR_PATTERN, $value) ?: []), static fn (string $item): bool => '' !== $item));
    }

    /**
     * A web address (http or https), null for an empty cell.
     */
    public static function url(string $value, EventColumn $column): ?string
    {
        if ('' === $value) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, \PHP_URL_SCHEME));
        if (!\in_array($scheme, ['http', 'https'], true) || false === filter_var($value, \FILTER_VALIDATE_URL)) {
            throw new InvalidCellException($column, \sprintf('« %s » n’est pas une adresse web (https://…).', $value));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public static function emails(string $value, EventColumn $column): array
    {
        $emails = self::list($value);
        foreach ($emails as $email) {
            if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new InvalidCellException($column, \sprintf('« %s » n’est pas une adresse e-mail.', $email));
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    public static function urls(string $value, EventColumn $column): array
    {
        return array_map(static fn (string $url): string => (string) self::url($url, $column), self::list($value));
    }
}
