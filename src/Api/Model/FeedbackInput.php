<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use Silarhi\AppJsBundle\Attribute\ConstantsToJs;
use Symfony\Component\Validator\Constraints as Assert;

#[ConstantsToJs]
final readonly class FeedbackInput
{
    /** The shortest message, which the form checks before sending it */
    public const int MESSAGE_MIN_LENGTH = 10;

    public function __construct(
        #[Assert\NotBlank(message: 'Veuillez saisir votre message.')]
        #[Assert\Length(min: self::MESSAGE_MIN_LENGTH, minMessage: 'Votre message doit faire au moins {{ limit }} caractères')]
        public string $message = '',
    ) {
    }
}
