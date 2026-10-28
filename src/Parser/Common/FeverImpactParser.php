<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use App\Utils\DecimalNumber;
use App\Utils\SessionHours;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fever (Candlelight, The Jazz Room, The Jury Experience, its partners' shows) through its affiliate program on
 * Impact. Its catalogue "2024 Impact Catalog" lists every plan of every country (36,301 on 2026-10-28, 2,379 in
 * France): one item per plan, every session of it, the venue's address and coordinates, an affiliate link. Read
 * country by country from the Partner API ("impact.client" scoped client); the files of the catalogue are only served
 * over FTP.
 *
 * Left out: the gift cards (a plan tagged "Gift Cards", at a "Localisation secrète"), the vouchers without a venue
 * (cinema seats at a "Localisation secrète") and the plans whose sales earn nothing ("Tier 4: Non-commissionable
 * Plans", "Tier 5: Restricted Commission Plans"): those are the venues' own concerts (6MIC, Rocher de Palmer), which
 * Fnac and the other feeds sell as well.
 */
final class FeverImpactParser extends AbstractParser
{
    private const int CATALOG_ID = 15532;

    /**
     * The countries of the site, as the catalogue names them.
     */
    private const array COUNTRIES = [
        'France' => 'FR',
        'Belgium' => 'BE',
        'Switzerland' => 'CH',
        'Monaco' => 'MC',
    ];

    /**
     * The tiers whose sales earn a commission (the first words of the "Category" column).
     */
    private const array COMMISSIONED_TIERS = ['Tier 1:', 'Tier 2:', 'Tier 3:'];

    /**
     * The most sessions a day lists one by one: a Candlelight plays three times an evening at most (2026-10-28), past
     * that the sessions are entries to a visit or an exhibition, every half hour or quarter of an hour.
     */
    private const int LISTED_SESSIONS_PER_DAY = 3;

    /**
     * The countries an address may end with ("1 Rue Porte Reine, 73000 Chambéry, France"), or be ("France// 75004").
     */
    private const array COUNTRY_NAMES = ['France', 'Belgique', 'Belgium', 'Suisse', 'Switzerland', 'Monaco'];

    /**
     * A part of an address that is no town: a number, a street ("Rue de Rivoli", "Waldenburgerstrasse") or a landmark
     * ("Port de Solférino", "Jardin Tuileries"). A word joined by a hyphen names a town: "Château-Thierry", "Port-Vendres".
     */
    private const string NO_TOWN = '/\d|^\P{L}*$|^(?:au|cour|rue|avenue|av\.|boulevard|bd|place|quai|chemin|all[ée]es?|impasse|route|cours|square|passage|promenade|esplanade|parvis|port|pont|jardin|parc|pyramide|[ée]glise|centre|ch[âa]teau|sortie|galerie|mus[ée]e|th[ée][âa]tre|tous|[îi]le-de-france)\b(?!-)|(?:strasse|straße|straat|laan|plein|gasse|platz|weg|street|road)\b/iu';

    /**
     * The type of a plan, from the first of its tags ("Subcategory", in no order) that names one. The others are
     * Fever's marketing ("Mother's Day", "On A Budget", "Fever Originals").
     */
    private const array TYPES = [
        'Candlelight Concerts' => 'Concert',
        'Concerts' => 'Concert',
        'Stand-Up Comedy' => 'Humour',
        'Theatre' => 'Théâtre',
        'Theater' => 'Théâtre',
        'Ballet' => 'Danse',
        'Night Clubs' => 'Soirée',
        'Nightlife & Party' => 'Soirée',
        'Immersive Experiences & Exhibits' => 'Exposition',
        'Cinema' => 'Cinéma',
        'Tasting Experiences' => 'Dégustation',
        'Food & Drink' => 'Gastronomie',
        'City Tours' => 'Visite',
        'Comedy & Shows' => 'Spectacle',
        'Live Shows' => 'Spectacle',
    ];

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        #[Target('impact.client')]
        private readonly HttpClientInterface $impactClient,
        #[Autowire(env: 'IMPACT_ACCOUNT_SID')]
        private readonly string $accountSid,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Fever';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getCommandName(): string
    {
        return 'impact.fever';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function fetchEvents(?DateTimeImmutable $since, bool $includePast): iterable
    {
        foreach (array_keys(self::COUNTRIES) as $country) {
            foreach ($this->items($country) as $item) {
                $data = self::columns($item);

                // A malformed item is logged and skipped; a failure to publish stops the run
                yield $this->mapRecord(fn (): ?EventDto => $this->arrayToDto($data), ['sku' => $data['SKU']]);
            }
        }
    }

    /**
     * The items of a country, page after page (the API serves ~600 an answer whatever the PageSize asked: 4 calls for
     * France's 2,379 on 2026-10-28).
     *
     * @return iterable<array<string, mixed>>
     */
    private function items(string $country): iterable
    {
        $url = \sprintf('Mediapartners/%s/Catalogs/%d/Items', $this->accountSid, self::CATALOG_ID);
        $query = ['PageSize' => 1000, 'Query' => \sprintf("Text1 = '%s'", $country)];
        while (null !== $url) {
            $page = $this->impactClient->request('GET', $url, ['query' => $query])->toArray();
            yield from $page['Items'] ?? [];

            // The next page's path holds its query
            $url = ($page['@nextpageuri'] ?? '') ?: null;
            $query = [];
        }
    }

    /**
     * An item of the API with the columns of the catalogue as Fever submits it (the "advertiser format" its CSV
     * keeps): Impact files them under its own product fields, the sessions as the "Manufacturer", the venue as the
     * "Material", the coordinates as the "Pattern".
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, string>
     */
    private static function columns(array $item): array
    {
        $first = static fn (mixed $values): string => \is_array($values) ? (string) ($values[0] ?? '') : '';

        return [
            'Country' => (string) ($item['Text1'] ?? ''),
            'City' => (string) ($item['Text2'] ?? ''),
            'Venue' => (string) ($item['Material'] ?? ''),
            'Location' => (string) ($item['ShippingLabel'] ?? ''),
            'Coordinates' => (string) ($item['Pattern'] ?? ''),
            'SKU' => (string) ($item['CatalogItemId'] ?? ''),
            'Plan Name' => (string) ($item['Name'] ?? ''),
            'Affiliate URL' => (string) ($item['Url'] ?? ''),
            'Fever URL' => $first($item['Colors'] ?? null),
            'Description' => (string) ($item['Description'] ?? ''),
            'Sessions' => (string) ($item['Manufacturer'] ?? ''),
            'Currency' => (string) ($item['Currency'] ?? ''),
            'Ticket prices' => implode(',', \is_array($item['Labels'] ?? null) ? $item['Labels'] : []),
            'Availability of tickets' => $first($item['Bullets'] ?? null),
            'Category' => (string) ($item['Category'] ?? ''),
            'Subcategory' => (string) ($item['SubCategory'] ?? ''),
            'Additional Notes' => (string) ($item['Text3'] ?? ''),
            'Photo 1' => (string) ($item['ImageUrl'] ?? ''),
            'Photo 2' => $first($item['AdditionalImageUrls'] ?? null),
        ];
    }

    /**
     * @param array<string, string> $data
     */
    private function arrayToDto(array $data): ?EventDto
    {
        $countryCode = self::COUNTRIES[$data['Country']] ?? null;
        $tags = array_map(trim(...), explode(',', $data['Subcategory'] ?? ''));
        $venueName = trim($data['Venue'] ?? '');
        if (null === $countryCode
            || '' === $venueName
            || \in_array('Gift Cards', $tags, true)
            || 1 === preg_match('/^(?:localisation secr[eè]te|secret location)\b/iu', $venueName)
            || !array_any(self::COMMISSIONED_TIERS, static fn (string $tier): bool => str_starts_with($data['Category'] ?? '', $tier))
        ) {
            return null;
        }

        $sessions = self::sessions($data['Sessions'] ?? '');
        if ([] === $sessions) {
            return null;
        }

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['SKU'];
        $event->name = $data['Plan Name'];
        $event->description = nl2br(trim($data['Description'] ?? ''));
        $event->type = self::type($tags);
        $event->source = $data['Affiliate URL'];
        $event->imageUrl = self::originalPicture($data['Photo 1'] ?? '') ?? self::originalPicture($data['Photo 2'] ?? '');
        $event->prices = self::formatPriceRange(self::ticketPrices($data['Ticket prices'] ?? ''), $data['Currency'] ?: 'EUR');

        $days = array_keys($sessions);
        $event->startDate = new DateTimeImmutable($days[0]);
        $event->endDate = new DateTimeImmutable($days[\count($days) - 1]);
        foreach ($sessions as $day => $times) {
            array_push($event->timesheets, ...self::timesheetsOfDay(new DateTimeImmutable($day), $times));
        }

        [$street, $town, $postalCode] = self::address($data['Location'] ?? '', $data['City'] ?? '');
        [$latitude, $longitude] = self::coordinates($data['Coordinates'] ?? '');

        $country = new CountryDto();
        $country->code = $countryCode;

        $city = new CityDto();
        $city->name = $town;
        $city->postalCode = $postalCode;
        $city->country = $country;

        $place = new PlaceDto();
        $place->name = $venueName;
        $place->street = $street;
        $place->latitude = $latitude;
        $place->longitude = $longitude;
        $place->externalId = sha1(\sprintf('%s %s %s', $venueName, $data['Location'] ?? '', $countryCode));
        $place->city = $city;
        $place->country = $country;

        $event->place = $place;

        return $event;
    }

    /**
     * The sessions of a plan by day, "2027-02-26 21:30, 2027-02-26 23:00" as ['2027-02-26' => [21:30, 23:00]], the
     * days and their times in order. A plan has up to 1,559 of them (a spa open every half hour for seven months).
     *
     * @return array<string, list<DateTimeImmutable>> the times keep their own date (the time is what counts)
     */
    private static function sessions(string $sessions): array
    {
        $days = [];
        foreach (explode(',', $sessions) as $session) {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($session));
            if (false !== $start) {
                $days[$start->format('Y-m-d')][] = $start;
            }
        }

        ksort($days);
        foreach ($days as $day => $times) {
            sort($times);
            $days[$day] = $times;
        }

        return $days;
    }

    /**
     * The timesheets of one day of a plan: a concert playing at 18h and at 20h30 is two sessions; an exhibition letting
     * visitors in every quarter of an hour from 10h to 18h45 is one, its first entry and a label that goes on from it
     * ("À 10h, puis toutes les 15 min jusqu'à 18h45"), rather than 36 lines on its page (116k timesheets for the plans
     * of the site instead of 54k).
     *
     * @param list<DateTimeImmutable> $times the start of each session of the day, in order (at least one)
     *
     * @return list<EventTimesheetDto>
     */
    private static function timesheetsOfDay(DateTimeImmutable $day, array $times): array
    {
        $hours = null;
        if (\count($times) > self::LISTED_SESSIONS_PER_DAY) {
            $hours = self::laterEntries($times);
            $times = [$times[0]];
        }

        return array_map(static function (DateTimeImmutable $time) use ($day, $hours): EventTimesheetDto {
            $timesheet = new EventTimesheetDto();
            $timesheet->startAt = $day;
            $timesheet->endAt = $day;
            $timesheet->startTime = $time;
            $timesheet->hours = $hours;

            return $timesheet;
        }, $times);
    }

    /**
     * The entries of a day after its first, as SessionHours writes them after "À 10h, ": "puis toutes les 15 min
     * jusqu'à 18h45" when they follow at a steady pace, else "puis 35 autres séances jusqu'à 18h45". The last entry is
     * no end (the visit goes on past it): it stays in the label. Two days of the same hours get the same label, so
     * that an exhibition open alike every day shows them once, as the event's.
     *
     * @param list<DateTimeImmutable> $times at least two, in order
     */
    private static function laterEntries(array $times): string
    {
        $gaps = [];
        for ($i = 1, $count = \count($times); $i < $count; ++$i) {
            $gaps[] = intdiv($times[$i]->getTimestamp() - $times[$i - 1]->getTimestamp(), 60);
        }

        $gap = array_unique($gaps);
        $pace = match (true) {
            1 !== \count($gap) => \sprintf('%d autres séances', \count($times) - 1),
            60 === $gap[0] => 'toutes les heures',
            0 === $gap[0] % 60 => \sprintf('toutes les %d heures', intdiv($gap[0], 60)),
            default => \sprintf('toutes les %d min', $gap[0]),
        };

        return \sprintf('puis %s %s', $pace, mb_strtolower((string) SessionHours::format(null, $times[\count($times) - 1])));
    }

    /**
     * "Rue Pascal Duverger, Aix-en-Provence// 13090": the street, the town and its postal code. The "City" column is
     * Fever's market, not the town: a venue in Lyon is sold in Annecy's, "Brussels" is "Bruxelles" in the address.
     * The last part of the address is the town when it reads as one ("Saint-Émilion// 33330"), else the address has
     * none ("Champ de Mars, 5 Avenue Anatole France// 75007", "Jardin Tuileries// 75001") and the town is the market:
     * the postal code would not find it, Paris sharing each of its codes with its arrondissement (CityComparator).
     *
     * @return array{?string, ?string, ?string} the street, the town, the postal code
     */
    private static function address(string $location, string $market): array
    {
        [$address, $postalCode] = array_pad(explode('//', $location, 2), 2, '');
        $postalCode = trim($postalCode);
        // "00000", "0000" for the plans without an address
        if (1 !== preg_match('/^\d{4,5}$/', $postalCode) || 0 === (int) $postalCode) {
            $postalCode = null;
        }

        $parts = array_values(array_filter(array_map(trim(...), explode(',', $address)), static fn (string $part): bool => '' !== $part));
        // "1 Rue Porte Reine, 73000 Chambéry, France", "36 Rue Dalayrac, Paris, 2" (the arrondissement)
        while ([] !== $parts && (\in_array($parts[\count($parts) - 1], self::COUNTRY_NAMES, true) || ctype_digit($parts[\count($parts) - 1]))) {
            array_pop($parts);
        }

        $town = (string) preg_replace('/^\d{4,5}\s+/', '', $parts[\count($parts) - 1] ?? '');
        if ('' !== $town && 1 !== preg_match(self::NO_TOWN, $town)) {
            array_pop($parts);
        } else {
            $town = $market;
        }

        return [[] === $parts ? null : implode(', ', $parts), '' === $town ? null : $town, $postalCode];
    }

    /**
     * "(43.5192132; 5.4224053)", or none for "(0.0000000; 0.0000000)".
     *
     * @return array{?float, ?float}
     */
    private static function coordinates(string $coordinates): array
    {
        if (1 !== preg_match('/^\(\s*(-?[\d.]+)\s*;\s*(-?[\d.]+)\s*\)$/', trim($coordinates), $matches)) {
            return [null, null];
        }

        $latitude = DecimalNumber::fromValue($matches[1]);
        $longitude = DecimalNumber::fromValue($matches[2]);

        return 0.0 === $latitude && 0.0 === $longitude ? [null, null] : [$latitude, $longitude];
    }

    /**
     * "28.61 EUR, 30.81 EUR": the price of each ticket. The "Minimum Price" column is not the lowest of them.
     *
     * @return list<string>
     */
    private static function ticketPrices(string $prices): array
    {
        preg_match_all('/\d+(?:\.\d+)?/', $prices, $matches);

        return $matches[0];
    }

    /**
     * Fever's pictures are served by Cloudinary as 550px squares ("…/image/upload/f_auto,w_550,h_550/fever2/plan/…"):
     * without the transformation, the picture as uploaded (768px for a Candlelight).
     */
    private static function originalPicture(string $url): ?string
    {
        $url = trim($url);

        return '' === $url ? null : (string) preg_replace('#/image/upload/[^/]+/(?=fever2/)#', '/image/upload/', $url);
    }

    /**
     * @param list<string> $tags
     */
    private static function type(array $tags): ?string
    {
        foreach (self::TYPES as $tag => $type) {
            if (\in_array($tag, $tags, true)) {
                return $type;
            }
        }

        return null;
    }
}
