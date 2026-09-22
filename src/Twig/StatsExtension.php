<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Twig;

use App\Repository\EventRepository;
use MessageFormatter;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class StatsExtension
{
    private const int EVENTS_COUNT_TTL = 86_400; // 1 day

    public function __construct(
        private EventRepository $eventRepository,
        private CacheInterface $memoryCache,
    ) {
    }

    /**
     * Human-readable total of referenced events, e.g. "1,9 million d'événements".
     */
    #[AsTwigFunction(name: 'events_count_label')]
    public function eventsCountLabel(): string
    {
        // Cache the raw count (not the label) so wording changes never need a cache flush.
        $count = $this->memoryCache->get('stats.events_count', function (ItemInterface $item): int {
            $item->expiresAfter(self::EVENTS_COUNT_TTL);

            return $this->eventRepository->countActiveEvents();
        });

        return self::formatEventsCount($count);
    }

    /**
     * Turns a raw event count into the noun phrase used after "Plus de …".
     */
    public static function formatEventsCount(int $count): string
    {
        // From a million: compact long form ("1,9 million", "2,1 millions") floored to 100K so "Plus de …"
        // never overstates, and "de" as after any "million". Below, the whole count, grouped by thousands.
        return MessageFormatter::formatMessage(
            'fr',
            <<<'ICU'
                {scale, select,
                    millions {{count, number, ::compact-long .# rounding-mode-floor} d''événements}
                    other {{count, plural, one {# événement} other {# événements}}}
                }
                ICU,
            ['scale' => $count >= 1_000_000 ? 'millions' : 'other', 'count' => $count],
        ) ?: throw new RuntimeException(intl_get_error_message());
    }
}
