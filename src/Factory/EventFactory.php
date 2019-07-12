<?php

namespace App\Factory;

use App\Entity\Event;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EventFactory
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @return Event
     */
    public function fromArray(array $datas)
    {
        $event = new Event();

        foreach ($datas as $field => $value) {
            //Cas spécial : traitement d'un datetime
            if (\is_array($value) && isset($value['date'])) {
                $value = new \DateTime($value['date'], new \DateTimeZone($value['timezone']));
            }
            $this->propertyAccessor->setValue($event, $field, $value);
        }

        return $event;
    }
}
