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
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContentRemovalRequestInput
{
    /**
     * @param string[] $eventUrls
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Veuillez entrer votre adresse email')]
        #[Assert\Email(message: 'Veuillez entrer une adresse email valide')]
        public string $email = '',

        #[Assert\NotNull(message: 'Veuillez sélectionner le type de contenu à supprimer')]
        public ?ContentRemovalType $type = null,

        #[Assert\NotBlank(message: 'Veuillez entrer votre message')]
        #[Assert\Length(min: 10, minMessage: 'Votre message doit faire au moins {{ limit }} caractères')]
        public string $message = '',

        #[Assert\All([
            new Assert\Url(message: 'Veuillez entrer une URL valide', requireTld: true),
        ])]
        public array $eventUrls = [],
    ) {
    }
}
