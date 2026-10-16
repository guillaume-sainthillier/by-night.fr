<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\EventExportScope;
use App\Enum\EventExportStatus;
use App\Enum\PersonalEventFilter;
use App\Enum\TransferFormat;
use App\Repository\EventExportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A file of a member's events, written by a worker (App\Transfer\Export) into transfers.storage. Its spreadsheets
 * have the import's columns: exported, changed, then imported back, the events are updated, not duplicated.
 */
#[ORM\Entity(repositoryClass: EventExportRepository::class)]
#[ORM\Index(name: 'event_export_status_idx', columns: ['status', 'updated_at'])]
class EventExport
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: EventExportStatus::class)]
    private EventExportStatus $status = EventExportStatus::Pending;

    #[ORM\Column(type: Types::STRING, length: 8, enumType: TransferFormat::class)]
    private ?TransferFormat $format = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: EventExportScope::class)]
    private EventExportScope $scope = EventExportScope::All;

    /** The search of the member's list it was started from, if any */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $searchQuery = null;

    /** The status filter of the member's list it was started from, if any */
    #[ORM\Column(type: Types::STRING, length: 16, nullable: true, enumType: PersonalEventFilter::class)]
    private ?PersonalEventFilter $statusFilter = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $exportedCount = 0;

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

    public function getStatus(): EventExportStatus
    {
        return $this->status;
    }

    public function setStatus(EventExportStatus $status): self
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

    public function getScope(): EventExportScope
    {
        return $this->scope;
    }

    public function setScope(EventExportScope $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    public function setSearchQuery(?string $searchQuery): self
    {
        $this->searchQuery = null === $searchQuery || '' === trim($searchQuery) ? null : mb_substr(trim($searchQuery), 0, 255);

        return $this;
    }

    public function getStatusFilter(): ?PersonalEventFilter
    {
        return $this->statusFilter;
    }

    public function setStatusFilter(?PersonalEventFilter $statusFilter): self
    {
        $this->statusFilter = $statusFilter;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;

        return $this;
    }

    public function getExportedCount(): int
    {
        return $this->exportedCount;
    }

    public function setExportedCount(int $exportedCount): self
    {
        $this->exportedCount = $exportedCount;

        return $this;
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
     * The name the file is downloaded under.
     */
    public function getDownloadName(): string
    {
        return \sprintf('by-night-evenements-%s.%s', $this->getCreatedAt()?->format('Y-m-d') ?? 'export', $this->format?->value ?? 'csv');
    }
}
