<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * The agenda pages of a kind of outing (/{location}/agenda/sortir/{slug}). Few events have a category, so a type page
 * searches the events naming one of its synonyms instead.
 *
 * The value names the type in the query string of the pages it narrows down ("?type=student" on a venue), in the
 * stored types of an event (Event::$agendaTypes) and in the index; the path of its own page keeps the French slug the
 * search engines know.
 */
enum AgendaType: string
{
    case Concert = 'concert';
    case Show = 'show';
    case Exhibition = 'exhibition';
    case Family = 'family';
    case Student = 'student';

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->getSlug() === $slug) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The path segment of the type page: /{location}/agenda/sortir/{slug}.
     */
    public function getSlug(): string
    {
        return match ($this) {
            self::Concert => 'concert',
            self::Show => 'spectacle',
            self::Exhibition => 'exposition',
            self::Family => 'famille',
            self::Student => 'etudiant',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Concert => 'Concerts',
            self::Show => 'Spectacles',
            self::Exhibition => 'Expositions',
            self::Family => 'Sorties en famille',
            self::Student => 'Soirées étudiantes',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Concert => 'lucide:music',
            self::Show => 'lucide:drama',
            self::Exhibition => 'lucide:landmark',
            self::Family => 'lucide:baby',
            self::Student => 'lucide:party-popper',
        };
    }

    /**
     * The synonyms the page searches with, on top of the keywords typed.
     *
     * @return list<string>
     */
    public function getTerms(): array
    {
        return match ($this) {
            self::Concert => ['concert', 'musique', 'artiste'],
            self::Show => ['spectacle', 'exposition', 'théâtre', 'comédie'],
            self::Exhibition => ['exposition', 'salon'],
            self::Family => ['famille', 'enfants'],
            self::Student => ['soirée', 'étudiant', 'bar', 'discothèque', 'boîte de nuit', 'after work'],
        };
    }
}
