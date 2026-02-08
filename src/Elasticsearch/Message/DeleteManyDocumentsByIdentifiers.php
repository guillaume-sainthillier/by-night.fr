<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Message;

final class DeleteManyDocumentsByIdentifiers extends DocumentsAction
{
    /** @var string[] */
    private readonly array $documentIds;

    /**
     * @param array<string|int> $documentIds
     */
    public function __construct(
        string $indexName,
        array $documentIds,
        private readonly bool|string $routing,
    ) {
        parent::__construct($indexName);
        // Elastica 8 requires string IDs
        $this->documentIds = array_map(strval(...), $documentIds);
    }

    /**
     * @return string[]
     */
    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    public function getRouting(): bool|string
    {
        return $this->routing;
    }
}
