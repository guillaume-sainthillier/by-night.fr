<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Model;

use App\Enum\ContentRemovalType;
use Silarhi\AppJsBundle\Attribute\ConstantsToJs;
use Symfony\Component\Validator\Constraints as Assert;

#[ConstantsToJs]
final readonly class ContentRemovalRequestInput
{
    /** The shortest message, which the form checks before sending it */
    public const int MESSAGE_MIN_LENGTH = 10;

    /**
     * @param string[] $eventUrls
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Veuillez saisir votre adresse e-mail.')]
        #[Assert\Email(message: 'Veuillez saisir une adresse e-mail valide.')]
        public string $email = '',

        #[Assert\NotNull(message: 'Veuillez sélectionner le type de contenu à supprimer')]
        public ?ContentRemovalType $type = null,

        #[Assert\NotBlank(message: 'Veuillez saisir votre message.')]
        #[Assert\Length(min: self::MESSAGE_MIN_LENGTH, minMessage: 'Votre message doit faire au moins {{ limit }} caractères')]
        public string $message = '',

        #[Assert\All([
            new Assert\Url(message: 'Veuillez saisir une URL valide.', requireTld: true),
        ])]
        public array $eventUrls = [],
    ) {
    }
}
