<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

/**
 * What a member does at once with several of their events (their list, an import's report).
 */
enum EventBulkAction: string
{
    case Publish = 'publish';
    case Draft = 'draft';
    case Delete = 'delete';

    /**
     * What was done, for the flash message (ICU, "count" events).
     */
    public function getDoneMessage(): string
    {
        return match ($this) {
            self::Publish => '{count, plural, =0 {Aucun événement publié.} one {Un événement publié : il est en ligne.} other {{count, number} événements publiés : ils sont en ligne.}}',
            self::Draft => '{count, plural, =0 {Aucun événement masqué.} one {Un événement passé en brouillon : il n’est plus visible sur le site.} other {{count, number} événements passés en brouillon : ils ne sont plus visibles sur le site.}}',
            self::Delete => '{count, plural, =0 {Aucun événement supprimé.} one {Un événement supprimé.} other {{count, number} événements supprimés.}}',
        };
    }
}
