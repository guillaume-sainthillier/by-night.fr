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
 * The pages whose deletion is recorded (DeletedPage), to answer 410 Gone.
 */
enum DeletedPageKind: string
{
    /** /{location}/soiree/{slug}--{id}: by id */
    case Event = 'event';
    /** /membres/{slug}--{id}: by id */
    case User = 'user';
    /** /organisateurs/{slug} */
    case Publisher = 'publisher';
    /** /{location}/agenda/sortir-a/{slug}: slugs are only unique within a location, which is part of the identifier */
    case Place = 'place';
}
