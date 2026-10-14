<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

final class EventTimesheetDto
{
    #[Assert\NotBlank(message: 'Vous devez indiquer une date de début')]
    public ?DateTimeImmutable $startAt = null;

    #[Assert\NotBlank(message: 'Vous devez indiquer une date de fin')]
    public ?DateTimeImmutable $endAt = null;

    /** The time the session starts (its date is not read), when the source gives one */
    public ?DateTimeImmutable $startTime = null;

    /** The time the session ends (its date is not read): earlier than the start, it goes past midnight */
    public ?DateTimeImmutable $endTime = null;

    /** What the times cannot say ("À 20h, de 21h à minuit"): a plain slot goes in the times */
    public ?string $hours = null;
}
