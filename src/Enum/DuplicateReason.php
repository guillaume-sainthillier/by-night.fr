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
 * Why an event redirects to another one, as EventFamilyResolver links them. A link without a reason was made by hand
 * (the back office) or before the identity hash: the resolver leaves it as it is.
 */
enum DuplicateReason: string
{
    /** Another record of the same source for the same event: they share an identity hash */
    case SameIdentity = 'same_identity';

    /** Another source sells or lists the same show (CrossSourceLink) */
    case SameShow = 'same_show';
}
