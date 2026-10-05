<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\Country;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Enum\EventStatus;
use App\Picture\EventProfilePicture;
use App\Utils\HtmlExcerpter;
use DateTimeImmutable;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class EventJsonLd
{
    /** Long enough for any real description, short enough not to weigh on the page twice */
    private const int DESCRIPTION_LENGTH = 5000;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EventProfilePicture $eventProfilePicture,
        private HtmlExcerpter $htmlExcerpter,
        private EventSchemaType $eventSchemaType,
    ) {
    }

    public function generateEventJsonLd(Event $event): string
    {
        $schema = $this->generateEventSchema($event);

        return $this->toJson($schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateEventSchema(Event $event): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $this->eventSchemaType->resolve($event),
            'name' => $event->getName(),
            'url' => $this->generateEventUrl($event),
            'eventStatus' => $this->mapEventStatus($event->getStatus()),
            'eventAttendanceMode' => EventStatus::MovedOnline === $event->getStatus()
                ? 'https://schema.org/OnlineEventAttendanceMode'
                : 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ($event->getStartDate() instanceof DateTimeImmutable) {
            // DATE columns: the day alone, a midnight time would read as the actual start or end. With the time of the
            // first session when the source gives it, and no offset: Google reads such a time in the time zone of the
            // event's place, which also holds for the overseas departments
            $schema['startDate'] = $event->getStartDate()->format('Y-m-d') . ($event->getStartTime()?->format('\\TH:i') ?? '');
        }

        $description = $this->htmlExcerpter->excerpt($event->getDescription(), self::DESCRIPTION_LENGTH);
        if ('' !== $description) {
            $schema['description'] = $description;
        }

        $endDate = $event->getEndDate() ?? $event->getStartDate();
        if ($endDate instanceof DateTimeImmutable) {
            $schema['endDate'] = $this->endDate($event, $endDate);
        }

        if ($event->hasImage()) {
            $schema['image'] = $this->eventProfilePicture->getOriginalPicture($event);
        }

        $schema['location'] = $this->buildLocationSchema($event);

        $offer = $this->buildOfferSchema($event);
        if (null !== $offer) {
            $schema['offers'] = $offer;
        }

        // 0 is a free entry; null an unknown price, not a free one (StartingPrice)
        if (0.0 === $event->getStartingPrice()) {
            $schema['isAccessibleForFree'] = true;
        }

        // A group or one artist, the sources do not say: PerformingGroup, as Google's examples name the artists of a bill
        $performers = $event->getPerformers();
        if ([] !== $performers) {
            $schema['performer'] = array_map(static fn (string $name): array => ['@type' => 'PerformingGroup', 'name' => $name], $performers);
        }

        if ($event->getUser() instanceof User) {
            $schema['organizer'] = $this->buildOrganizerSchema($event);
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLocationSchema(Event $event): array
    {
        $location = [
            '@type' => 'Place',
            'name' => $event->getPlaceName() ?? 'Lieu non communiqué',
        ];

        if ($event->getPlace() instanceof Place) {
            $location['url'] = $this->urlGenerator->generate('app_agenda_by_place', [
                'placeSlug' => $event->getPlace()->getSlug(),
                'location' => $event->getLocationSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $address = ['@type' => 'PostalAddress'];
        $hasAddress = false;

        if ($event->getPlaceStreet()) {
            $address['streetAddress'] = $event->getPlaceStreet();
            $hasAddress = true;
        }

        if ($event->getPlaceCity()) {
            $address['addressLocality'] = $event->getPlaceCity();
            $hasAddress = true;
        }

        if ($event->getPlacePostalCode()) {
            $address['postalCode'] = $event->getPlacePostalCode();
            $hasAddress = true;
        }

        if ($event->getPlaceCountry() instanceof Country) {
            $address['addressCountry'] = $event->getPlaceCountry()->getId();
            $hasAddress = true;
        }

        if ($hasAddress) {
            $location['address'] = $address;
        }

        if ($event->getLatitude() && $event->getLongitude()) {
            $location['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
            ];
        }

        return $location;
    }

    /**
     * The tickets, from the lowest price the sources give. A cancelled event sells none, and an event whose price is
     * unknown only gets an offer when it is sold out, which is worth telling on its own.
     *
     * @return array<string, mixed>|null
     */
    private function buildOfferSchema(Event $event): ?array
    {
        $status = $event->getStatus();
        $price = $event->getStartingPrice();
        $soldOut = EventStatus::SoldOut === $status;

        if (EventStatus::Cancelled === $status || (null === $price && !$soldOut)) {
            return null;
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $this->ticketUrl($event) ?? $this->generateEventUrl($event),
            'availability' => $soldOut ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
        ];

        if (null !== $price) {
            // StartingPrice only reads amounts in euros
            $offer['price'] = $price;
            $offer['priceCurrency'] = 'EUR';
        }

        return $offer;
    }

    /**
     * Where to book the event: our affiliate link for the ticketing feeds, as the event page's button, else the
     * ticketing the source gives.
     */
    private function ticketUrl(Event $event): ?string
    {
        return ($event->isAffiliate() ? $event->getSource() : null) ?? $event->getTicketUrl();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrganizerSchema(Event $event): array
    {
        $user = $event->getUser();

        return [
            '@type' => 'Person',
            'name' => $user->getUsername(),
            'url' => $this->urlGenerator->generate('app_user_index', [
                'id' => $user->getId(),
                'slug' => $user->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /**
     * The last day, with the time the last session ends when the source gives it (no offset, as the start). An end not
     * after the start, the same day, goes past midnight (21:00 to 02:00): it is on the next day.
     */
    private function endDate(Event $event, DateTimeImmutable $endDate): string
    {
        $endTime = $event->getEndTime();
        if (null === $endTime) {
            return $endDate->format('Y-m-d');
        }

        $end = $endDate->setTime((int) $endTime->format('G'), (int) $endTime->format('i'));
        $startTime = $event->getStartTime();
        $start = $event->getStartDate()?->setTime((int) $startTime?->format('G'), (int) $startTime?->format('i'));
        if (null !== $start && $end <= $start) {
            $end = $end->modify('+1 day');
        }

        return $end->format('Y-m-d\\TH:i');
    }

    private function mapEventStatus(?EventStatus $status): string
    {
        return $status?->getSchemaOrgStatus() ?? 'https://schema.org/EventScheduled';
    }

    private function generateEventUrl(Event $event): string
    {
        return $this->urlGenerator->generate('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function toJson(array $schema): string
    {
        return json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
