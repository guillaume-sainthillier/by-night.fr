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
 * Decides which event pages search engines may index and which events the sitemap submits.
 *
 * Every published event page stays indexable whatever its age: most of the site's organic
 * clicks land on events that ended years ago (venues, recurring nights, evergreen queries).
 * The sitemap, on the other hand, only submits upcoming events and those that ended within
 * the grace period: it steers the crawl budget to new content, while older pages keep their
 * place in the index on their own merit.
 */
final readonly class EventIndexingPolicy
{
    public function __construct(
        private ClockInterface $clock,
        #[Autowire(param: 'app.seo.event_sitemap_grace_days')]
        private int $sitemapGraceDays = 30,
    ) {
    }

    /**
     * Earliest end date (inclusive) an event may have and still be submitted in the sitemap.
     */
    public function getSitemapSince(): DateTimeImmutable
    {
        return $this->today()->modify(\sprintf('-%d days', $this->sitemapGraceDays));
    }

    /**
     * Drafts are not public and duplicates redirect: neither belongs in the index.
     */
    public function isIndexable(Event $event): bool
    {
        return $event->isIndexable();
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
