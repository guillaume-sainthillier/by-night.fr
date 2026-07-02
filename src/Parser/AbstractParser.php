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
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

abstract class AbstractParser implements ParserInterface
{
    private int $parsedEvents = 0;

    private int $skippedEvents = 0;

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
     */
    public function publish(EventDto $eventDto): void
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
