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
class InsertManyDocumentsHandler extends AbstractActionHandler
{
    public function __invoke(InsertManyDocuments $action): void
    {
        $persister = $this->getPersister($action);
        $persister->doInsertMany($action->getDocuments());
    }
}
