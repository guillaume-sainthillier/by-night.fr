<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Contracts;

use App\Dto\EventDto;

interface ParserInterface
{
    public static function getParserName(): string;

    public static function getParserVersion(): string;

    public function getName(): string;

    public function parse(bool $incremental): void;

    public function getParsedEvents(): int;

    public function publish(EventDto $eventDto): void;

    public function getCommandName(): string;
}
