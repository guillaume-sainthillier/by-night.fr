<?php

/*
 * This file is part of By Night.
 * (c) 2013-2025 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class EventDateTimeDto
{
    #[Assert\NotBlank(message: 'Vous devez donner une date et heure de début')]
    public ?DateTimeInterface $startDateTime = null;

    #[Assert\NotBlank(message: 'Vous devez donner une date et heure de fin')]
    public ?DateTimeInterface $endDateTime = null;

    public function __construct(
        ?DateTimeInterface $startDateTime = null,
        ?DateTimeInterface $endDateTime = null
    ) {
        $this->startDateTime = $startDateTime;
        $this->endDateTime = $endDateTime;
    }
}
