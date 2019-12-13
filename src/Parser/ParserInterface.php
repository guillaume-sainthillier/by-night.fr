<?php

namespace App\Parser;

interface ParserInterface
{
    public static function getParserName(): string;

    public function parse(bool $incremental): void;

    public function getParsedEvents(): int;

    public function publish(array $item): void;
}
