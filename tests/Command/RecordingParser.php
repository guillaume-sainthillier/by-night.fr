<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\Contracts\ParserInterface;
use DateTimeImmutable;
use RuntimeException;

/**
 * A parser that only records the $since it is given, for EventsImportCommandTest.
 */
final class RecordingParser implements ParserInterface
{
    /**
     * @var list<DateTimeImmutable|null> one entry per parse() call
     */
    public array $runs = [];

    public function __construct(
        private readonly string $commandName,
        private readonly bool $enabled = true,
        private readonly bool $failing = false,
        private readonly int $parsedEvents = 0,
        private readonly int $failedRecords = 0,
    ) {
    }

    // @phpstan-ignore shipmonk.deadMethod (interface contract, not exercised by the command)
    public static function getParserName(): string
    {
        return 'Recording';
    }

    // @phpstan-ignore shipmonk.deadMethod (interface contract, not exercised by the command)
    public static function getParserVersion(): string
    {
        return '1.0';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getName(): string
    {
        return \sprintf('Recording %s', $this->commandName);
    }

    public function parse(?DateTimeImmutable $since): void
    {
        $this->runs[] = $since;

        if ($this->failing) {
            throw new RuntimeException('Source unavailable');
        }
    }

    public function getParsedEvents(): int
    {
        return $this->parsedEvents;
    }

    public function getFailedRecords(): int
    {
        return $this->failedRecords;
    }

    public function getSkippedEvents(): int
    {
        return 0;
    }

    public function getCommandName(): string
    {
        return $this->commandName;
    }
}
