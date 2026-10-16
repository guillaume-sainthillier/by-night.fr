<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\EventImportStatus;
use App\Enum\TransferFormat;
use App\Repository\EventImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A member's spreadsheet of events, checked then imported by a worker (App\Transfer\Import). Its files live in
 * transfers.storage: the original, then the check's two reports, the rows it kept (what the import reads) and the
 * rows it turned down with their errors. Its counters and lists are the report its page shows, live.
 *
 * @phpstan-type RowProblem array{row: int, reference: ?string, column: ?string, message: string, url?: string}
 * @phpstan-type EventOutcome array{row: int, reference: ?string, id: ?int, name: string, outcome: string}
 */
#[ORM\Entity(repositoryClass: EventImportRepository::class)]
#[ORM\Index(name: 'event_import_status_idx', columns: ['status', 'updated_at'])]
class EventImport
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    /** The problems listed on the page; the error file holds them all */
    public const int MAX_LISTED_PROBLEMS = 200;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: EventImportStatus::class)]
    private EventImportStatus $status = EventImportStatus::Uploaded;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: TransferFormat::class)]
    private ?TransferFormat $format = null;

    /** The file's name on the member's computer */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $filePath = null;

    /** The rows the check kept: the import reads them, not the original */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $successFilePath = null;

    /** The rows the check turned down, with an "Erreurs" column */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $errorFilePath = null;

    /** The file's rows, its header left out */
    #[ORM\Column(type: Types::INTEGER)]
    private int $rowCount = 0;

    /** The events the rows describe (rows sharing an ID or a reference are one event) */
    #[ORM\Column(type: Types::INTEGER)]
    private int $eventCount = 0;

    /** The events the running phase (check or import) went through */
    #[ORM\Column(type: Types::INTEGER)]
    private int $processedCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $validCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $invalidCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $warningCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $createdCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $updatedCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unchangedCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $failedCount = 0;

    /** The events the member chose not to import (warned of on the check), by their first row */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $skippedCount = 0;

    /** @var list<int>|null the first rows of the events the member chose not to import */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $skippedRows = null;

    /** @var list<RowProblem> */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /** @var list<RowProblem> */
    #[ORM\Column(type: Types::JSON)]
    private array $warnings = [];

    /** @var list<EventOutcome> what the import did with each event */
    #[ORM\Column(type: Types::JSON)]
    private array $outcomes = [];

    /** Why the check or the import stopped, for the member */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): EventImportStatus
    {
        return $this->status;
    }

    public function setStatus(EventImportStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getFormat(): ?TransferFormat
    {
        return $this->format;
    }

    public function setFormat(TransferFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getSuccessFilePath(): ?string
    {
        return $this->successFilePath;
    }

    public function setSuccessFilePath(?string $successFilePath): self
    {
        $this->successFilePath = $successFilePath;

        return $this;
    }

    public function getErrorFilePath(): ?string
    {
        return $this->errorFilePath;
    }

    public function setErrorFilePath(?string $errorFilePath): self
    {
        $this->errorFilePath = $errorFilePath;

        return $this;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function setRowCount(int $rowCount): self
    {
        $this->rowCount = $rowCount;

        return $this;
    }

    public function getEventCount(): int
    {
        return $this->eventCount;
    }

    public function setEventCount(int $eventCount): self
    {
        $this->eventCount = $eventCount;

        return $this;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function setProcessedCount(int $processedCount): self
    {
        $this->processedCount = $processedCount;

        return $this;
    }

    public function getValidCount(): int
    {
        return $this->validCount;
    }

    public function getInvalidCount(): int
    {
        return $this->invalidCount;
    }

    public function getWarningCount(): int
    {
        return $this->warningCount;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getUnchangedCount(): int
    {
        return $this->unchangedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * @return list<int>
     */
    public function getSkippedRows(): array
    {
        return $this->skippedRows ?? [];
    }

    /**
     * @param list<int> $rows the first rows of the events not to import
     */
    public function setSkippedRows(array $rows): self
    {
        $rows = array_values(array_unique(array_filter($rows, static fn (int $row): bool => $row > 1)));
        sort($rows);
        $this->skippedRows = [] === $rows ? null : $rows;

        return $this;
    }

    /**
     * @return list<RowProblem>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<RowProblem>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return list<EventOutcome>
     */
    public function getOutcomes(): array
    {
        return $this->outcomes;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    /**
     * Starts the check over: a new check forgets what the previous one found.
     */
    public function startCheck(): self
    {
        $this->status = EventImportStatus::Checking;
        $this->processedCount = 0;
        $this->validCount = 0;
        $this->invalidCount = 0;
        $this->warningCount = 0;
        $this->errors = [];
        $this->warnings = [];
        $this->failureReason = null;

        return $this;
    }

    /**
     * An event the check went through, valid or not, with what it found about it.
     *
     * @param list<RowProblem> $errors
     * @param list<RowProblem> $warnings
     */
    public function addCheckedEvent(array $errors, array $warnings): self
    {
        ++$this->processedCount;
        [] === $errors ? ++$this->validCount : ++$this->invalidCount;
        $this->warningCount += \count($warnings);
        $this->errors = \array_slice([...$this->errors, ...$errors], 0, self::MAX_LISTED_PROBLEMS);
        $this->warnings = \array_slice([...$this->warnings, ...$warnings], 0, self::MAX_LISTED_PROBLEMS);

        return $this;
    }

    /**
     * Starts the import, or resumes it: the events already written come out unchanged the second time.
     */
    public function startImport(): self
    {
        $this->status = EventImportStatus::Importing;
        $this->processedCount = 0;
        $this->createdCount = 0;
        $this->updatedCount = 0;
        $this->unchangedCount = 0;
        $this->failedCount = 0;
        $this->skippedCount = 0;
        $this->outcomes = [];
        $this->failureReason = null;

        return $this;
    }

    /**
     * @param EventOutcome $outcome
     */
    public function addOutcome(array $outcome): self
    {
        ++$this->processedCount;
        match ($outcome['outcome']) {
            'created' => ++$this->createdCount,
            'updated' => ++$this->updatedCount,
            'unchanged' => ++$this->unchangedCount,
            'skipped' => ++$this->skippedCount,
            default => ++$this->failedCount,
        };
        $this->outcomes[] = $outcome;

        return $this;
    }
}
