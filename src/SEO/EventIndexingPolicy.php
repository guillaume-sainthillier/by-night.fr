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
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decides which event pages are worth a search engine's attention.
 *
 * Every event page stays online for visitors and inbound links. Only the pages this policy
 * accepts are submitted in the sitemap and left indexable; the others carry a "noindex", so
 * the crawl budget and the site's quality signals go to live content instead of the millions
 * of past events Google already declines to index.
 */
final readonly class EventIndexingPolicy
{
    public function __construct(
        private ClockInterface $clock,
        #[Autowire(param: 'app.seo.event_index_grace_days')]
        private int $graceDays = 30,
    ) {
    }

    /**
     * Earliest end date (inclusive) an event may have and still be indexable: the grace
     * period lets Google recrawl a just-finished event and see it end before it is dropped.
     */
    public function getIndexableSince(): DateTimeImmutable
    {
        return $this->today()->modify(\sprintf('-%d days', $this->graceDays));
    }

    public function isIndexable(Event $event): bool
    {
        if (!$event->isIndexable()) {
            // Drafts and duplicates: the page redirects or is not public anyway
            return false;
        }

        $endDate = $event->getEndDate() ?? $event->getStartDate();

        return null === $endDate || $endDate >= $this->getIndexableSince();
    }

    public function hasEnded(Event $event): bool
    {
        $endDate = $event->getEndDate() ?? $event->getStartDate();

        return null !== $endDate && $endDate < $this->today();
    }

    private function today(): DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }
}
