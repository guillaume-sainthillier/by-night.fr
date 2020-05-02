<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Factory;

use App\Entity\Event;
use DateTime;
use DateTimeZone;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class EventFactory
{
    private PropertyAccessor $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function fromArray(array $datas): Event
    {
        $event = new Event();

        foreach ($datas as $field => $value) {
            //Cas spécial : traitement d'un datetime
            if (\is_array($value) && isset($value['date'])) {
                $value = new DateTime($value['date'], new DateTimeZone($value['timezone']));
            }
            $this->propertyAccessor->setValue($event, $field, $value);
        }

        return $event;
    }
}
