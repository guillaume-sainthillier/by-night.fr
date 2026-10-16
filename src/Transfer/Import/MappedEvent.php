<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Import;

use App\Dto\EventDto;

/**
 * An event of a member's spreadsheet, read: what it would save, or why it cannot.
 */
final readonly class MappedEvent
{
    /**
     * @param int|null         $eventId  the member's event it updates, null for a new one
     * @param bool|null        $draft    null: a new event is a draft, an existing one keeps its state
     * @param bool             $changed  whether the rows change the existing event (a new one is a change)
     * @param list<RowProblem> $errors
     * @param list<RowProblem> $warnings
     */
    public function __construct(
        public EventRowGroup $group,
        public EventDto $dto,
        public ?int $eventId,
        public ?string $reference,
        public ?bool $draft,
        public bool $changed,
        public array $errors,
        public array $warnings = [],
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function withWarnings(RowProblem ...$warnings): self
    {
        return new self($this->group, $this->dto, $this->eventId, $this->reference, $this->draft, $this->changed, $this->errors, [...$this->warnings, ...$warnings]);
    }
}
