<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * A comment none of the spam rules of App\Utils\SpamDetector catches: loan offers, mediums and spells.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class NotSpamComment extends Constraint
{
    public string $message = 'Ce commentaire est considéré comme du spam ou à caractère commercial.';
}
