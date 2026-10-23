<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Contracts\ParserInterface;
use App\Parser\AffiliateParsers;
use App\Parser\Common\TestParser;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

/**
 * The figures of the advertisers' page, counted once a day: the events to come and their cities and countries from
 * the stored counts (UpcomingEventCounter, recounted every night), the members and what they published, and the
 * sources the parsers import. Kept as plain values, so a change of AdvertiserFigures never needs a cache flush.
 *
 * The page must render when the database or the cache fails: the figures are then null, and the page hides them.
 */
final readonly class AdvertiserFiguresProvider
{
    public const string CACHE_KEY = 'stats.advertiser_figures';

    private const int TTL = 86_400; // 1 day

    /**
     * @param iterable<ParserInterface> $parsers
     */
    public function __construct(
        private CountryRepository $countryRepository,
        private CityRepository $cityRepository,
        private EventRepository $eventRepository,
        private UserRepository $userRepository,
        private CacheInterface $memoryCache,
        private LoggerInterface $logger,
        #[AutowireIterator(ParserInterface::class)]
        private iterable $parsers,
    ) {
    }

    /**
     * @phpstan-impure counted again once the cached figures expire
     */
    public function get(): ?AdvertiserFigures
    {
        try {
            return AdvertiserFigures::fromArray($this->memoryCache->get(self::CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::TTL);

                return $this->count()->toArray();
            }));
        } catch (Throwable $exception) {
            // Nothing is cached when the count throws: the next visitor counts again
            $this->logger->warning('The advertisers\' figures could not be counted: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

            return null;
        }
    }

    private function count(): AdvertiserFigures
    {
        $upcoming = $this->countryRepository->getUpcomingTotals();
        $agendas = [];
        $ticketing = [];
        foreach ($this->parsers as $parser) {
            // The development tool that publishes 100 "test" events: no source
            if ($parser instanceof TestParser) {
                continue;
            }

            $name = $parser::getParserName();
            if (AffiliateParsers::isAffiliate($name)) {
                $ticketing[] = $name;
            } else {
                $agendas[] = $name;
            }
        }

        sort($agendas);

        return new AdvertiserFigures(
            upcomingEvents: $upcoming['events'],
            cities: $this->cityRepository->countWithUpcomingEvents(),
            countries: $upcoming['countries'],
            members: $this->userRepository->countEnabled(),
            memberEvents: $this->eventRepository->getMemberTotals()['events'],
            agendaSources: $agendas,
            ticketingSources: $ticketing,
        );
    }
}
