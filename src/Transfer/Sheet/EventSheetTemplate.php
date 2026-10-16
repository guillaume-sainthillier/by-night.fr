<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

use App\Enum\TransferFormat;
use DateTimeImmutable;

/**
 * The blank spreadsheet a member fills in: every column, and two examples, an event on two dates (two rows sharing
 * a reference) and an event on one.
 */
final readonly class EventSheetTemplate
{
    public function __construct(private SheetWriter $writer)
    {
    }

    public function write(string $path, TransferFormat $format): void
    {
        $this->writer->write(
            $path,
            $format,
            array_map(static fn (EventColumn $column): string => $column->getLabel(), EventColumn::cases()),
            array_map(self::row(...), $this->examples()),
        );
    }

    /**
     * @param array<string, string> $values
     *
     * @return list<string>
     */
    private static function row(array $values): array
    {
        return array_map(static fn (EventColumn $column): string => $values[$column->value] ?? '', EventColumn::cases());
    }

    /**
     * @return list<array<string, string>>
     */
    private function examples(): array
    {
        $first = new DateTimeImmutable('first friday of next month');
        $concert = [
            EventColumn::Reference->value => 'CONCERT-JAZZ',
            EventColumn::Name->value => 'Soirée jazz au bord de l’eau',
            EventColumn::Description->value => 'Deux soirs de jazz en plein air, avec le quartet de la maison.',
            EventColumn::StartTime->value => '20:30',
            EventColumn::EndTime->value => '23:00',
            EventColumn::Place->value => 'La Guinguette',
            EventColumn::Street->value => '1 quai de la Daurade',
            EventColumn::PostalCode->value => '31000',
            EventColumn::City->value => 'Toulouse',
            EventColumn::Country->value => 'FR',
            EventColumn::Category->value => 'Concert',
            EventColumn::Themes->value => 'Jazz, Plein air',
            EventColumn::Prices->value => '12 € / 8 € tarif réduit',
            EventColumn::TicketUrl->value => 'https://example.com/billetterie',
            EventColumn::Draft->value => 'oui',
        ];

        return [
            [EventColumn::StartDate->value => $first->format('d/m/Y')] + $concert,
            // The event's second date: its other columns may stay empty
            [
                EventColumn::Reference->value => 'CONCERT-JAZZ',
                EventColumn::StartDate->value => $first->modify('+1 day')->format('d/m/Y'),
                EventColumn::StartTime->value => '20:30',
                EventColumn::EndTime->value => '23:00',
            ],
            [
                EventColumn::Reference->value => 'EXPO-PHOTO',
                EventColumn::Name->value => 'Exposition photo : la ville la nuit',
                EventColumn::Description->value => 'Trente tirages de la ville après minuit, en accès libre.',
                EventColumn::StartDate->value => $first->format('d/m/Y'),
                EventColumn::EndDate->value => $first->modify('+30 days')->format('d/m/Y'),
                EventColumn::Hours->value => 'Du mardi au dimanche, de 14h à 19h',
                EventColumn::Place->value => 'Galerie du Château d’eau',
                EventColumn::Street->value => '1 place Laganne',
                EventColumn::PostalCode->value => '31300',
                EventColumn::City->value => 'Toulouse',
                EventColumn::Category->value => 'Exposition',
                EventColumn::Prices->value => 'Gratuit',
                EventColumn::Draft->value => 'oui',
            ],
        ];
    }
}
