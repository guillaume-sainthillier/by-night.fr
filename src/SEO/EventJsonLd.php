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
use App\Enum\AgendaType;
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
            '@type' => $this->schemaType($event),
            'name' => $event->getName(),
            'url' => $this->generateEventUrl($event),
            'eventStatus' => $this->mapEventStatus($event->getStatus()),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ($event->getStartDate() instanceof DateTimeImmutable) {
            // DATE columns: the day alone, a midnight time would read as the actual start or end
            $schema['startDate'] = $event->getStartDate()->format('Y-m-d');
        }

        $description = $this->htmlExcerpter->excerpt($event->getDescription(), self::DESCRIPTION_LENGTH);
        if ('' !== $description) {
            $schema['description'] = $description;
        }

        $endDate = $event->getEndDate() ?? $event->getStartDate();
        if ($endDate instanceof DateTimeImmutable) {
            $schema['endDate'] = $endDate->format('Y-m-d');
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
            // The ticketing page for the affiliates' events, the event page for the others
            'url' => $event->isAffiliate() && $event->getSource() ? $event->getSource() : $this->generateEventUrl($event),
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
     * The schema.org subtype of the event, from the agenda types the nightly classification found (AgendaType values).
     * Search engines treat every subtype as an Event: a wrong one costs more than the plain "Event", so only the
     * combinations whose events are one kind of outing count, as a sample of the production events shows:
     * - an exhibition is one, from the museum to the trade fair ("salon"), which ExhibitionEvent covers too, even when
     *   "spectacle" also made it a show; also a concert ("artistes"), it is as often a festival or a fundraiser;
     * - a concert is music, unless it is also a show (stand-up and plays among the musicals) or an exhibition;
     * - a show mixes theatre, stand-up, dance and circus, and the family and student types match words ("famille",
     *   "soirée") more than audiences: they say nothing of the kind of event.
     */
    private function schemaType(Event $event): string
    {
        $types = array_filter(array_map(AgendaType::tryFrom(...), $event->getAgendaTypes()));

        $concert = \in_array(AgendaType::Concert, $types, true);
        $exhibition = \in_array(AgendaType::Exhibition, $types, true);

        return match (true) {
            $exhibition && !$concert => 'ExhibitionEvent',
            $concert && !$exhibition && !\in_array(AgendaType::Show, $types, true) => 'MusicEvent',
            default => 'Event',
        };
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
