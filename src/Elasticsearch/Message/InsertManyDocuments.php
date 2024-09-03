<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Message;

use Elastica\Document;

final class InsertManyDocuments extends DocumentsAction
{
    /**
     * @param Document[] $documents
     */
    public function __construct(
        string $indexName,
        private readonly array $documents,
    ) {
        parent::__construct($indexName);
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }
}
