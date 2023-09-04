<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Message;

class DeleteManyByIdentifierDocuments extends DocumentsAction
{
    /**
     * @param string[] $documentIds
     */
    public function __construct(
        string $indexName,
        private readonly array $documentIds,
        private readonly bool|string $routing,
    ) {
        parent::__construct($indexName);
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
