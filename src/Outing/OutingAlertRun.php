<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use App\Entity\UserEvent;

/**
 * What a run of the outing alerts did (OutingChangeNotifier::sendFlagged()): the alerts sent, those that could not
 * leave, which the next run tries again, and those skipped for an address the mailer refuses, which no run tries again.
 */
final class OutingAlertRun
{
    private int $sent = 0;

    /** @var list<array{event: ?int, member: ?int, email: ?string, error: string}> */
    private array $failures = [];

    /** @var list<array{event: ?int, member: ?int, email: ?string, error: string}> */
    private array $skipped = [];

    public function recordSent(): void
    {
        ++$this->sent;
    }

    public function recordFailure(UserEvent $outing, string $error): void
    {
        $this->failures[] = self::row($outing, $error);
    }

    public function recordSkipped(UserEvent $outing, string $error): void
    {
        $this->skipped[] = self::row($outing, $error);
    }

    public function getSent(): int
    {
        return $this->sent;
    }

    /**
     * @return list<array{event: ?int, member: ?int, email: ?string, error: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @return list<array{event: ?int, member: ?int, email: ?string, error: string}>
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * @return array{event: ?int, member: ?int, email: ?string, error: string}
     */
    private static function row(UserEvent $outing, string $error): array
    {
        return [
            'event' => $outing->getEvent()?->getId(),
            'member' => $outing->getUser()?->getId(),
            'email' => $outing->getUser()?->getEmail(),
            'error' => $error,
        ];
    }
}
