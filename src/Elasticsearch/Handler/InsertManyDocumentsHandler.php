<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\Message\InsertManyDocuments;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InsertManyDocumentsHandler extends AbstractActionHandler
{
    public function __invoke(InsertManyDocuments $action): void
    {
        $entities = $this->fetchEntities($action->getEntityClass(), $action->getEntityIds());

        if ([] === $entities) {
            return;
        }

        $this->getPersister($action)->doInsertMany($entities);
    }
}
