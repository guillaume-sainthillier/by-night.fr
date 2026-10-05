<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Contracts\ParserInterface;
use App\Repository\ParserStateRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * Runs a parser from its watermark, the start of its previous successful run (ParserStateRepository), so that an
 * incremental import fetches exactly what its source changed since then, however many runs were missed. Only a run
 * that completed and could read most of its records moves the watermark: after a failure the next run fetches the
 * same window again, and the dedup gate absorbs the overlap.
 */
final readonly class ParserRunner
{
    /** Above this share of unreadable records, the run is a mapping bug rather than a few bad records */
    private const int MAX_UNREADABLE_PERCENT = 10;

    public function __construct(
        private ParserStateRepository $parserStates,
    ) {
    }

    /**
     * Where the next run of the parser starts: its watermark, or null for its whole catalogue (a first run, or $full).
     */
    public function getSince(ParserInterface $parser, bool $full): ?DateTimeImmutable
    {
        return $full ? null : $this->parserStates->findLastParsedAt($parser->getCommandName());
    }

    /**
     * @param bool $includePast also the events already over, which a source leaves out by default (a backfill)
     *
     * @throws RuntimeException when most records could not be read; the parser's own failures are thrown as they are.
     *                          Either way, the watermark stays.
     */
    public function run(ParserInterface $parser, ?DateTimeImmutable $since, bool $includePast = false): void
    {
        // The watermark is the run *start*: whatever the source changes while we are fetching is picked up by the
        // next run rather than lost in between.
        $startedAt = new DateTimeImmutable();

        $parser->parse($since, $includePast);

        // A few records the parser could not map are logged and left out (AbstractParser::mapRecord()), and come back
        // with their next change at the source. Most of them failing is a mapping bug instead: the window is imported
        // again once the parser is fixed.
        $failedRecords = $parser->getFailedRecords();
        $records = $parser->getParsedEvents() + $parser->getSkippedEvents() + $failedRecords;
        if (100 * $failedRecords > self::MAX_UNREADABLE_PERCENT * $records) {
            throw new RuntimeException(\sprintf('%s could not read %d of its %d records', $parser->getName(), $failedRecords, $records));
        }

        $this->parserStates->markParsed($parser->getCommandName(), $startedAt);
    }
}
