<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\Message\DeleteManyDocumentsByIdentifiers;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteManyDocumentsByIdentifiersHandler extends AbstractActionHandler
{
    public function __invoke(DeleteManyDocumentsByIdentifiers $action): void
    {
        $persister = $this->getPersister($action);
        $persister->doDeleteManyByIdentifiers($action->getDocumentIds(), $action->getRouting());
    }
}
