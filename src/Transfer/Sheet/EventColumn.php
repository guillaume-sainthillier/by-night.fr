<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Sheet;

/**
 * The columns of the members' event spreadsheets: the template, the exports and what an import reads. A column is
 * found by its header, wherever it is and however it is written ("Date de début", "date de debut"); a column the
 * file lacks is left alone on the events it updates.
 *
 * Rows sharing an ID (or, without one, a reference) are one event: each row is one of its sessions (the session
 * columns), the other columns describe the event and must agree between its rows, or be left empty after the first.
 */
enum EventColumn: string
{
    case Id = 'id';
    case Reference = 'reference';
    case Name = 'name';
    case Description = 'description';
    case StartDate = 'start_date';
    case EndDate = 'end_date';
    case StartTime = 'start_time';
    case EndTime = 'end_time';
    case Hours = 'hours';
    case Place = 'place';
    case Street = 'street';
    case PostalCode = 'postal_code';
    case City = 'city';
    case Country = 'country';
    case Category = 'category';
    case Themes = 'themes';
    case Prices = 'prices';
    case TicketUrl = 'ticket_url';
    case ImageUrl = 'image_url';
    case Websites = 'websites';
    case Phones = 'phones';
    case Emails = 'emails';
    case Status = 'status';
    case StatusMessage = 'status_message';
    case Draft = 'draft';

    /** The values that hold several items ("Jazz, Blues"): split on commas, semicolons and line breaks */
    public const string LIST_SEPARATOR_PATTERN = '/[,;\r\n]+/';

    public function getLabel(): string
    {
        return match ($this) {
            self::Id => 'ID',
            self::Reference => 'Référence externe',
            self::Name => 'Nom',
            self::Description => 'Description',
            self::StartDate => 'Date de début',
            self::EndDate => 'Date de fin',
            self::StartTime => 'Heure de début',
            self::EndTime => 'Heure de fin',
            self::Hours => 'Horaires',
            self::Place => 'Lieu',
            self::Street => 'Adresse',
            self::PostalCode => 'Code postal',
            self::City => 'Ville',
            self::Country => 'Pays',
            self::Category => 'Catégorie',
            self::Themes => 'Thèmes',
            self::Prices => 'Tarifs',
            self::TicketUrl => 'Billetterie',
            self::ImageUrl => 'Image (URL)',
            self::Websites => 'Sites web',
            self::Phones => 'Téléphones',
            self::Emails => 'E-mails',
            self::Status => 'Statut',
            self::StatusMessage => 'Précision du statut',
            self::Draft => 'Brouillon',
        };
    }

    /** What the template says about the column (its header's comment in Excel, the help table on the page) */
    public function getHelp(): string
    {
        return match ($this) {
            self::Id => 'Laissez vide pour un nouvel événement. Rempli par l’export : la ligne met à jour cet événement.',
            self::Reference => 'Votre identifiant (billetterie, tableur…), unique parmi vos événements. Les lignes de même référence forment un seul événement à plusieurs dates.',
            self::Name => 'Obligatoire.',
            self::Description => 'Obligatoire, 10 caractères au moins.',
            self::StartDate => 'Obligatoire, JJ/MM/AAAA. Une ligne par date pour un événement à plusieurs dates.',
            self::EndDate => 'JJ/MM/AAAA, la date de début si vide.',
            self::StartTime, self::EndTime => 'HH:MM, facultatif.',
            self::Hours => 'Ce que les heures ne disent pas (« Ouverture des portes à 19h »).',
            self::Place => 'Nom du lieu, obligatoire.',
            self::Street => 'Numéro et rue.',
            self::PostalCode => 'Obligatoire en France.',
            self::City => 'Obligatoire.',
            self::Country => 'Code (FR, BE, CH…) ou nom, France si vide.',
            self::Category => 'Concert, Théâtre, Exposition…',
            self::Themes => 'Séparés par des virgules.',
            self::Prices => 'Texte libre (« 12 € / 8 € tarif réduit », « Gratuit »).',
            self::TicketUrl => 'Lien de la billetterie.',
            self::ImageUrl => 'Lien d’une image (JPEG, PNG, WebP), téléchargée après l’import. Vide : l’image actuelle est gardée.',
            self::Websites, self::Phones, self::Emails => 'Séparés par des virgules.',
            self::Status => 'Vide si l’événement a lieu comme prévu, sinon : ' . implode(', ', array_keys(EventStatusLabels::IMPORTABLE)) . '.',
            self::StatusMessage => 'Précise le statut (« Reporté au 12 mars »).',
            self::Draft => '« oui » pour garder l’événement hors ligne. Vide : brouillon pour un nouvel événement, inchangé sinon.',
        };
    }

    /** Whether the column belongs to each session (each row) rather than to the event */
    public function isSession(): bool
    {
        return \in_array($this, [self::StartDate, self::EndDate, self::StartTime, self::EndTime, self::Hours], true);
    }

    /** The columns an import cannot do without */
    public function isRequired(): bool
    {
        return self::Name === $this || self::StartDate === $this;
    }

    /**
     * The column a header names, written as the template does or close to it (case, accents, spaces, punctuation).
     */
    public static function fromHeader(string $header): ?self
    {
        static $columns = null;
        if (null === $columns) {
            $columns = [];
            foreach (self::cases() as $column) {
                $columns[self::normalize($column->getLabel())] = $column;
                $columns[self::normalize($column->value)] = $column;
            }
        }

        return $columns[self::normalize($header)] ?? null;
    }

    private static function normalize(string $header): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $header);

        return (string) preg_replace('/[^a-z0-9]+/', '', false === $ascii ? mb_strtolower($header) : $ascii);
    }
}
