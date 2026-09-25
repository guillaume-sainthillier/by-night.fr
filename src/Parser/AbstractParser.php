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
use App\Handler\EventHandler;
use App\Import\EventPublicationGuard;
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
    final public function parse(?DateTimeImmutable $since): void
    {
        $this->publishMany($this->fetchEvents($since));
    }

    /**
     * The source's events, best yielded as they are read: the stream is consumed chunk by
     * chunk, so a whole feed never sits in memory. A null entry (a row the parser could not
     * map) is skipped, which lets a parser yield its arrayToDto() results as they are.
     *
     * @param DateTimeImmutable|null $since see {@see ParserInterface::parse()}
     *
     * @return iterable<EventDto|null>
     */
    abstract protected function fetchEvents(?DateTimeImmutable $since): iterable;

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
     * @param iterable<EventDto|null> $eventDtos
     */
    private function publishMany(iterable $eventDtos): void
    {
        $chunk = [];
        foreach ($eventDtos as $eventDto) {
            if (null === $eventDto) {
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
