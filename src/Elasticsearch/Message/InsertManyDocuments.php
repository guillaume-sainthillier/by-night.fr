<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Message;

final class InsertManyDocuments extends DocumentsAction
{
    /**
     * @param class-string      $entityClass
     * @param array<string|int> $entityIds
     */
    public function __construct(
        string $indexName,
        private readonly string $entityClass,
        private readonly array $entityIds,
    ) {
        parent::__construct($indexName);
    }

    /**
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return array<string|int>
     */
    public function getEntityIds(): array
    {
        return $this->entityIds;
    }
}
