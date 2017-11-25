<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 13/09/2017
 * Time: 18:57.
 */

namespace AppBundle\Factory;

use AppBundle\Entity\Agenda;
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
     * @param array $datas
     *
     * @return Agenda
     */
    public function fromArray(array $datas)
    {
        $agenda = new Agenda();

        foreach ($datas as $field => $value) {
            $this->propertyAccessor->setValue($agenda, $field, $value);
        }

        return $agenda;
    }
}
