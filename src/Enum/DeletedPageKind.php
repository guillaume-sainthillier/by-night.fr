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
 * The pages found by a slug whose deletion is recorded (DeletedPage), to answer 410 Gone. Those found by an id (events,
 * members) need no record: an id up to the table's highest was given to a row since deleted.
 */
enum DeletedPageKind: string
{
    /** /organisateurs/{slug} */
    case Publisher = 'publisher';
    /** /{location}/agenda/sortir-a/{slug}: slugs are only unique within a location, which is part of the identifier */
    case Place = 'place';
}
