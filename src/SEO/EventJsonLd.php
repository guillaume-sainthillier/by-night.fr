<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Picture\EventProfilePicture;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class EventJsonLd
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EventProfilePicture $eventProfilePicture,
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
            '@type' => 'Event',
            'name' => $event->getName(),
            'url' => $this->generateEventUrl($event),
            'eventStatus' => $this->mapEventStatus($event->getStatus()),
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ($event->getStartDate()) {
            $schema['startDate'] = $event->getStartDate()->format('c');
        }

        if ($event->getDescription()) {
            $schema['description'] = strip_tags($event->getDescription());
        }

        $endDate = $event->getEndDate() ?? $event->getStartDate();
        if ($endDate) {
            $schema['endDate'] = $endDate->format('c');
        }

        if ($event->hasImage()) {
            $schema['image'] = $this->eventProfilePicture->getOriginalPicture($event);
        }

        $schema['location'] = $this->buildLocationSchema($event);

        if ($event->getUser()) {
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

        if ($event->getPlace()) {
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

        if ($event->getPlaceCountry()) {
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
