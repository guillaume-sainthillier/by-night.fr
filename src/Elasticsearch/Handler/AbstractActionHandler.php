<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\AsyncObjectPersister;
use App\Elasticsearch\Message\DocumentsAction;
use FOS\ElasticaBundle\Persister\PersisterRegistry;
use InvalidArgumentException;

abstract class AbstractActionHandler
{
    public function __construct(
        protected PersisterRegistry $registry
    ) {
    }

    protected function getPersister(DocumentsAction $action): AsyncObjectPersister
    {
        $persister = $this->registry->getPersister($action->getIndexName());
        if (!$persister instanceof AsyncObjectPersister) {
            throw new InvalidArgumentException(sprintf('No async persister was registered for index "%s".', $indexName));
        }

        return $persister;
    }
}
