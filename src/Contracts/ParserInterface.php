<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ParserInterface
{
    public static function getParserName(): string;

    public static function getParserVersion(): string;

    public function isEnabled(): bool;

    public function getName(): string;

    /**
     * Publishes the source's events.
     *
     * @param DateTimeImmutable|null $since when the previous successful run started; parsers that
     *                                      support incremental imports fetch only what changed
     *                                      since then, null asks for a full import
     */
    public function parse(?DateTimeImmutable $since): void;

    public function getParsedEvents(): int;

    public function getSkippedEvents(): int;

    /**
     * Records of the source the parser could not map, left out of the run.
     */
    public function getFailedRecords(): int;

    public function getCommandName(): string;
}
