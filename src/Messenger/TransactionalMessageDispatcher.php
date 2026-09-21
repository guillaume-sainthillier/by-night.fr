<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Messenger;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds messages back while a database transaction is open and releases them once
 * it is committed.
 *
 * An import batch ({@see \App\Handler\DoctrineEventHandler::handleMany()}) runs in one
 * transaction, but code called from inside it emits messages whose handlers read the
 * rows being written: image downloads scheduled after a flush, Elasticsearch documents
 * dispatched by the FOS Elastica postFlush listener. Sent immediately, such a message
 * can reach its worker before the commit, and the worker then finds nothing to load.
 *
 * Outside a transaction this is a plain pass-through to the bus.
 */
final class TransactionalMessageDispatcher implements ResetInterface
{
    private bool $inTransaction = false;

    /** @var object[] */
    private array $pending = [];

    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function begin(): void
    {
        $this->inTransaction = true;
    }

    /**
     * Dispatch now, or hold the message until commit() when a transaction is open.
     */
    public function dispatch(object $message): void
    {
        if ($this->inTransaction) {
            $this->pending[] = $message;

            return;
        }

        $this->messageBus->dispatch($message);
    }

    /**
     * Release every held message. Call only once the database transaction is committed.
     */
    public function commit(): void
    {
        $this->inTransaction = false;
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $message) {
            $this->messageBus->dispatch($message);
        }
    }

    /**
     * Drop every held message: the rows they refer to were rolled back.
     */
    public function rollBack(): void
    {
        $this->inTransaction = false;
        $this->pending = [];
    }

    public function reset(): void
    {
        $this->rollBack();
    }
}
