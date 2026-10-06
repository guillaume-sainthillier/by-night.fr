<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Ticketing;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Utils\HtmlFormatter;
use App\Utils\PriceLabel;
use App\Utils\StartingPrice;

/**
 * Where to book an event, cheapest first: one offer per ticketing site among the event and the rows redirecting to it
 * (its duplicates), read as the page renders. Nothing is copied onto the event: each row keeps the price, status and
 * link of its own source, and its import keeps them up to date.
 *
 * The ticketing feeds are booked through our affiliate link (the row's source). The organizer's own booking earns us
 * nothing: it is only offered when no ticketing site sells the event.
 */
final readonly class EventTicketOffers
{
    public function __construct(private HtmlFormatter $htmlFormatter)
    {
    }

    /**
     * @return list<TicketOffer>
     */
    public function forEvent(Event $event): array
    {
        $rows = [$event];
        foreach ($event->getDuplicates() as $duplicate) {
            if (!$duplicate->isRemovedAtSource()) {
                $rows[] = $duplicate;
            }
        }

        /** @var array<string, TicketOffer> $bySeller */
        $bySeller = [];
        foreach ($rows as $row) {
            $offer = $this->offer($row);
            if (null === $offer) {
                continue;
            }

            // One offer per site: the one to buy, at its lowest price
            $key = $offer->affiliate ? $offer->seller : $offer->url;
            if (!isset($bySeller[$key]) || TicketOffer::compare($offer, $bySeller[$key]) < 0) {
                $bySeller[$key] = $offer;
            }
        }

        $offers = array_values($bySeller);
        $onSale = array_filter($offers, static fn (TicketOffer $offer): bool => $offer->affiliate && !$offer->soldOut);
        if ([] !== $onSale) {
            $offers = array_values(array_filter($offers, static fn (TicketOffer $offer): bool => $offer->affiliate));
        }

        usort($offers, TicketOffer::compare(...));

        return $offers;
    }

    private function offer(Event $row): ?TicketOffer
    {
        if (EventStatus::Cancelled === $row->getStatus()) {
            return null;
        }

        if ($row->isAffiliate()) {
            $url = $row->getSource();
            $seller = self::sellerOf((string) $row->getFromData());
        } else {
            $url = $this->htmlFormatter->ensureProtocol($row->getTicketUrl());
            $seller = null !== $url ? self::hostOf($url) : null;
        }

        if (null === $url || '' === $url || null === $seller) {
            return null;
        }

        return new TicketOffer(
            $seller,
            $url,
            $row->isAffiliate(),
            // Its own prices: a canonical's starting price is the lowest of its family (EventFamilyResolver)
            StartingPrice::fromPrices($row->getPrices()),
            PriceLabel::fromPrices($row->getPrices()),
            EventStatus::SoldOut === $row->getStatus(),
        );
    }

    /**
     * The site, as its feed is named: "SeeTickets (ex Digitick)" is SeeTickets.
     */
    private static function sellerOf(string $parserName): string
    {
        return mb_trim((string) preg_replace('/\s*\(.*\)$/', '', $parserName));
    }

    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? (string) preg_replace('/^www\./', '', $host) : null;
    }
}
