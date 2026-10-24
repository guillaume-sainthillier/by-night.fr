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
 * What a run of the outing alerts did (OutingChangeNotifier::sendFlagged()): the alerts sent, and those that could not
 * leave, which the next run tries again.
 */
final class OutingAlertRun
{
    private int $sent = 0;

    /** @var list<array{event: ?int, member: ?int, email: ?string, error: string}> */
    private array $failures = [];

    public function recordSent(): void
    {
        ++$this->sent;
    }

    public function recordFailure(UserEvent $outing, string $error): void
    {
        $this->failures[] = [
            'event' => $outing->getEvent()?->getId(),
            'member' => $outing->getUser()?->getId(),
            'email' => $outing->getUser()?->getEmail(),
            'error' => $error,
        ];
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
}
