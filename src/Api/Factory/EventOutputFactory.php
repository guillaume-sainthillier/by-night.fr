<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Factory;

use App\Api\ApiResource\EventDetails;
use App\Api\Model\EventSession;
use App\Api\Model\EventSummary;
use App\Api\Model\EventVenue;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Utils\PriceLabel;
use DateTimeInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The events the API and the MCP tools answer: what a visitor reads on their card and their page.
 */
final readonly class EventOutputFactory
{
    /** The sessions the details list at most */
    private const int MAX_SESSIONS = 10;

    /** The characters of the description the details keep */
    private const int DESCRIPTION_LENGTH = 1500;

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @param ?DateTimeInterface $from the first day asked for: the session given is the first from then
     * @param ?DateTimeInterface $to   its last day
     */
    public function createSummary(Event $event, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): EventSummary
    {
        $price = PriceLabel::fromPrices($event->getPrices(), $event->getStartingPrice());
        $session = $event->getSessionFor($from, $to);

        return new EventSummary(
            id: (int) $event->getId(),
            name: (string) $event->getName(),
            url: $this->urlGenerator->generate('app_event_details', ['slug' => $event->getSlug(), 'id' => $event->getId(), 'location' => $event->getLocationSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            category: $event->getCategory()?->getName(),
            nextSession: null === $session ? null : EventSession::of($session),
            upcomingSessions: $event->countUpcomingSessions($from),
            venue: EventVenue::of($event),
            price: $price['label'] ?? null,
            free: $price['free'] ?? null,
            status: $event->getStatus()?->getLabel(),
            organizer: $event->getPublisher()?->getName(),
        );
    }

    /**
     * The card, its description, the sessions to come and the prices as the page lists them.
     */
    public function createDetails(Event $event, DateTimeInterface $today): EventDetails
    {
        $day = $today->format('Y-m-d');
        $sessions = array_filter(
            $event->getSessions(),
            static fn (EventTimesheet $session): bool => ($session->getEndAt() ?? $session->getStartAt())?->format('Y-m-d') >= $day,
        );
        $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $event->getDescription()), \ENT_QUOTES | \ENT_HTML5)) ?? '');

        return new EventDetails(
            event: $this->createSummary($event, $today),
            description: '' === $description ? null : (mb_strlen($description) > self::DESCRIPTION_LENGTH ? mb_substr($description, 0, self::DESCRIPTION_LENGTH) . '…' : $description),
            sessions: array_values(array_filter(array_map(EventSession::of(...), \array_slice(array_values($sessions), 0, self::MAX_SESSIONS)))),
            prices: $event->getPrices(),
            statusMessage: $event->getStatusMessage(),
        );
    }
}
