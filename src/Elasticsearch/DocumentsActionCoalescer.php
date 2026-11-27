<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use App\Elasticsearch\Message\DeleteManyDocumentsByIdentifiers;
use App\Elasticsearch\Message\DocumentsAction;
use App\Elasticsearch\Message\InsertManyDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use LogicException;

/**
 * Folds the Elasticsearch messages of a transaction into one message per index and action.
 *
 * Every flush of an import batch has the FOS Elastica listener send its own insert, replace and delete messages, each
 * costing its worker a few queries to load the documents again. Their handlers index what the database holds when
 * they run, so only the last action on a document matters: a deletion after an indexing wins, an indexing after a
 * deletion (the event became indexable again) wins, and an insertion stays one when the document is replaced next.
 */
final class DocumentsActionCoalescer
{
    private const string INSERT = 'insert';
    private const string REPLACE = 'replace';
    private const string DELETE = 'delete';

    /**
     * @param object[] $messages
     *
     * @return object[] the other messages in their order, each index's actions where its first one was
     */
    public static function coalesce(array $messages): array
    {
        /** @var array<string, array<string, array{string, string|bool, string|int}>> $documents by index, then by document id: its last action, its class (its routing for a deletion) and its id */
        $documents = [];
        /** @var list<object|string> $coalesced an index's name holds the place of its actions */
        $coalesced = [];

        foreach ($messages as $message) {
            if (!$message instanceof DocumentsAction) {
                $coalesced[] = $message;

                continue;
            }

            $indexName = $message->getIndexName();
            if (!isset($documents[$indexName])) {
                $documents[$indexName] = [];
                $coalesced[] = $indexName;
            }

            foreach (self::getActions($message) as [$action, $classOrRouting, $id]) {
                $previous = $documents[$indexName][(string) $id][0] ?? null;
                if (self::REPLACE === $action && self::INSERT === $previous) {
                    continue;
                }

                $documents[$indexName][(string) $id] = [$action, $classOrRouting, $id];
            }
        }

        $result = [];
        foreach ($coalesced as $message) {
            if (\is_object($message)) {
                $result[] = $message;

                continue;
            }

            array_push($result, ...self::buildMessages($message, $documents[$message]));
        }

        return $result;
    }

    /**
     * @return iterable<array{string, string|bool, string|int}>
     */
    private static function getActions(DocumentsAction $message): iterable
    {
        if ($message instanceof DeleteManyDocumentsByIdentifiers) {
            foreach ($message->getDocumentIds() as $id) {
                yield [self::DELETE, $message->getRouting(), $id];
            }

            return;
        }

        if (!$message instanceof InsertManyDocuments && !$message instanceof ReplaceManyDocuments) {
            throw new LogicException(\sprintf('Unable to coalesce a "%s" message', $message::class));
        }

        $action = $message instanceof InsertManyDocuments ? self::INSERT : self::REPLACE;
        foreach ($message->getEntityIds() as $id) {
            yield [$action, $message->getEntityClass(), $id];
        }
    }

    /**
     * @param array<string, array{string, string|bool, string|int}> $documents
     *
     * @return list<DocumentsAction>
     */
    private static function buildMessages(string $indexName, array $documents): array
    {
        /** @var array<string, array{string, string|bool, list<string|int>}> $groups by action and class (or routing) */
        $groups = [];
        foreach ($documents as [$action, $classOrRouting, $id]) {
            $key = $action . ' ' . json_encode($classOrRouting);
            $groups[$key] ??= [$action, $classOrRouting, []];
            $groups[$key][2][] = $id;
        }

        $messages = [];
        foreach ($groups as [$action, $classOrRouting, $ids]) {
            $messages[] = match ($action) {
                self::INSERT => new InsertManyDocuments($indexName, (string) $classOrRouting, $ids),
                self::REPLACE => new ReplaceManyDocuments($indexName, (string) $classOrRouting, $ids),
                self::DELETE => new DeleteManyDocumentsByIdentifiers($indexName, $ids, $classOrRouting),
                default => throw new LogicException('This code should not be reached!'),
            };
        }

        return $messages;
    }
}
