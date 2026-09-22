<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\ParserStateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-parser import watermark: when the last successful run of `app:events:import`
 * started. Parsers that support incremental imports fetch only what their source changed
 * since then, so a missed night is caught up on the next run instead of being lost.
 */
#[ORM\Entity(repositoryClass: ParserStateRepository::class)]
#[ORM\UniqueConstraint(name: 'parser_state_parser_unique', columns: ['parser'])]
class ParserState
{
    use EntityIdentityTrait;

    /**
     * @param string $parser the parser's command name, see ParserInterface::getCommandName()
     */
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 63)]
        private string $parser,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private DateTimeImmutable $lastParsedAt,
    ) {
    }

    public function getParser(): string
    {
        return $this->parser;
    }

    public function getLastParsedAt(): DateTimeImmutable
    {
        return $this->lastParsedAt;
    }

    public function setLastParsedAt(DateTimeImmutable $lastParsedAt): self
    {
        $this->lastParsedAt = $lastParsedAt;

        return $this;
    }
}
