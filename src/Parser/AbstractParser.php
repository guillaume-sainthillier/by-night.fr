<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser;

use App\Contracts\ParserInterface;
use App\Dto\EventDto;
use App\Dto\RemovedEventDto;
use App\Handler\EventHandler;
use App\Import\EventPublicationGuard;
use App\Message\RemoveSourceEvents;
use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

abstract class AbstractParser implements ParserInterface
{
    /**
     * Events checked against the dedup gate with one parser_data query.
     */
    private const int PUBLISH_CHUNK_SIZE = 500;

    private int $parsedEvents = 0;

    private int $skippedEvents = 0;

    private int $failedRecords = 0;

    private int $removedEvents = 0;

    private EventPublicationGuard $publicationGuard;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly EventHandler $eventHandler,
    ) {
    }

    /**
     * Setter injection keeps the dedup gate out of every parser's constructor.
     */
    #[Required]
    public function setPublicationGuard(EventPublicationGuard $publicationGuard): void
    {
        $this->publicationGuard = $publicationGuard;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return \sprintf('%s v%s', static::getParserName(), static::getParserVersion());
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserVersion(): string
    {
        return '1.0';
    }

    /**
     * {@inheritDoc}
     *
     * Final: every event goes through the chunked dedup gate of publishMany(); a parser only
     * says what its source holds, in fetchEvents().
     */
    final public function parse(?DateTimeImmutable $since, bool $includePast = false): void
    {
        $this->publishMany($this->fetchEvents($since, $includePast));
    }

    /**
     * The source's events, best yielded as they are read: the stream is consumed chunk by
     * chunk, so a whole feed never sits in memory. A null entry (a row the parser could not
     * map) is skipped, which lets a parser yield its arrayToDto() results as they are. A source
     * that says which of its records are gone yields a RemovedEventDto for each.
     *
     * @param DateTimeImmutable|null $since       see {@see ParserInterface::parse()}
     * @param bool                   $includePast see {@see ParserInterface::parse()}
     *
     * @return iterable<EventDto|RemovedEventDto|null>
     */
    abstract protected function fetchEvents(?DateTimeImmutable $since, bool $includePast): iterable;

    /**
     * Maps one record of the source. A record that cannot be mapped (a malformed date, a
     * missing field) is logged and left out rather than ending the run: one bad record kept a
     * whole source out, night after night, since a failed run does not move the watermark.
     * EventsImportCommand still fails a run whose records mostly failed (a mapping bug).
     *
     * @param Closure(): (EventDto|null) $map
     * @param array<string, mixed>       $context what identifies the record in the log
     */
    protected function mapRecord(Closure $map, array $context = []): ?EventDto
    {
        try {
            return $map();
        } catch (Throwable $exception) {
            ++$this->failedRecords;
            $this->logException($exception, $context);

            return null;
        }
    }

    private function publish(EventDto $eventDto): void
    {
        $eventDto->parserName = static::getParserName();
        $eventDto->parserVersion = static::getParserVersion();
        $eventDto->externalOrigin = $this->getCommandName();

        if (null !== $eventDto->place) {
            $eventDto->place->externalOrigin = $eventDto->externalOrigin;
        }

        $this->sanitize($eventDto);
        $this->eventHandler->cleanEvent($eventDto);

        // Dedup gate: skip enqueueing an event whose content is unchanged since the
        // previous run. Hashing happens here, after cleanEvent(), so the fingerprint
        // matches the one the consumer stores before its own re-clean pass — this holds
        // only because cleaning is idempotent (locked by CleanerTest).
        if (!$this->publicationGuard->shouldPublish($eventDto)) {
            ++$this->skippedEvents;

            return;
        }

        $this->messageBus->dispatch($eventDto);
        ++$this->parsedEvents;
    }

    /**
     * Publishes the stream chunk by chunk, each chunk checked against the previous run with
     * a single query rather than one per event.
     *
     * @param iterable<EventDto|RemovedEventDto|null> $eventDtos
     */
    private function publishMany(iterable $eventDtos): void
    {
        $chunk = [];
        /** @var array<string, list<string>> $removed external ids, by source prefix ('' for none) */
        $removed = [];
        foreach ($eventDtos as $eventDto) {
            if (null === $eventDto) {
                continue;
            }

            if ($eventDto instanceof RemovedEventDto) {
                $prefix = $eventDto->sourcePrefix ?? '';
                $removed[$prefix][] = $eventDto->externalId;
                if (\count($removed[$prefix]) >= self::PUBLISH_CHUNK_SIZE) {
                    $this->publishRemovals($removed[$prefix], $prefix);
                    unset($removed[$prefix]);
                }

                continue;
            }

            $chunk[] = $eventDto;
            if (\count($chunk) >= self::PUBLISH_CHUNK_SIZE) {
                $this->publishChunk($chunk);
                $chunk = [];
            }
        }

        if ([] !== $chunk) {
            $this->publishChunk($chunk);
        }

        foreach ($removed as $prefix => $externalIds) {
            $this->publishRemovals($externalIds, (string) $prefix);
        }
    }

    /**
     * @param list<string> $externalIds
     */
    private function publishRemovals(array $externalIds, string $sourcePrefix): void
    {
        $this->messageBus->dispatch(new RemoveSourceEvents(
            $this->getCommandName(),
            $externalIds,
            '' === $sourcePrefix ? null : $sourcePrefix,
        ));
        $this->removedEvents += \count($externalIds);
    }

    /**
     * @param list<EventDto> $eventDtos
     */
    private function publishChunk(array $eventDtos): void
    {
        // publish() stamps the origin: it is the command name, known before
        $this->publicationGuard->prefetch(
            $this->getCommandName(),
            array_map(static fn (EventDto $eventDto): ?string => $eventDto->externalId, $eventDtos),
        );

        try {
            foreach ($eventDtos as $eventDto) {
                $this->publish($eventDto);
            }
        } finally {
            $this->publicationGuard->reset();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    /**
     * Number of events skipped by the dedup gate because their content was unchanged.
     */
    public function getSkippedEvents(): int
    {
        return $this->skippedEvents;
    }

    /**
     * {@inheritDoc}
     */
    public function getRemovedEvents(): int
    {
        return $this->removedEvents;
    }

    /**
     * {@inheritDoc}
     */
    public function getFailedRecords(): int
    {
        return $this->failedRecords;
    }

    /**
     * Lower bound of an incremental fetch: the previous run start pushed back by a safety
     * margin, so clock skew with the source or its indexing lag cannot hide a change.
     * Re-fetching an unchanged event is free: the publication guard drops it.
     */
    protected static function withSafetyMargin(DateTimeImmutable $since): DateTimeImmutable
    {
        return $since->modify('-1 hour');
    }

    /**
     * A price as the feeds serve it ("27.5000", "39.00", "15.0"), without its trailing zeros:
     * "27.5", "39", "15".
     */
    protected static function formatPrice(float|string $price): string
    {
        return rtrim(rtrim(number_format((float) $price, 2, '.', ''), '0'), '.');
    }

    /**
     * The prices of the tickets of an event as a feed gives them, as one text: "22€", "De 15€ à 25€", or null for none.
     *
     * A 0 is no price, not a free entry: the affiliate feeds fill their price columns with it when they have none
     * (Fnac sells "Grévin - Billet Daté" at 22 € while a row of it says 0), and "0€" shows as "Gratuit" on the cards.
     *
     * @param list<float|string> $prices
     */
    protected static function formatPriceRange(array $prices): ?string
    {
        $prices = array_filter(array_map(floatval(...), $prices), static fn (float $price): bool => $price > 0);
        if ([] === $prices) {
            return null;
        }

        $min = self::formatPrice(min($prices));
        $max = self::formatPrice(max($prices));

        return $min === $max ? $min . '€' : \sprintf('De %s€ à %s€', $min, $max);
    }

    protected function logException(Throwable $exception, array $context = []): void
    {
        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'extra' => $context,
        ]);
    }

    private function sanitize(object $object): void
    {
        foreach ($object as $key => $value) {
            $object->{$key} = $this->getSanitizedValue($value);
        }
    }

    private function getSanitizedValue(mixed $value): mixed
    {
        if (\is_object($value)) {
            if ($value instanceof DateTimeInterface || $value instanceof BackedEnum) {
                return $value;
            }

            $this->sanitize($value);
        } elseif (\is_array($value)) {
            foreach ($value as $key => $itemValue) {
                $itemValue = $this->getSanitizedValue($itemValue);
                if (null !== $itemValue) {
                    $value[$key] = $itemValue;
                } else {
                    unset($value[$key]);
                }
            }
        } elseif (\is_string($value) && '' === trim($value)) {
            $value = null;
        }

        return $value;
    }
}
